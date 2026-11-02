<?php

namespace Tests\Feature\AssetModels\Api;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class UpdateAssetModelsTest extends TestCase
{
    public function testCanReplaceModelSpecificationsForTheCategoryFieldset()
    {
        $fieldset = CustomFieldset::factory()->create();
        $field = CustomField::factory()->create(['element' => 'text', 'format' => '', 'auto_add_to_fieldsets' => false]);
        $oldField = CustomField::factory()->create(['element' => 'text', 'format' => '', 'auto_add_to_fieldsets' => false]);
        $unsupportedField = CustomField::factory()->create([
            'element' => 'text',
            'format' => '',
            'is_unique' => true,
            'auto_add_to_fieldsets' => false,
        ]);
        $fieldset->fields()->attach([
            $field->id => ['order' => 1, 'required' => false],
            $oldField->id => ['order' => 2, 'required' => false],
            $unsupportedField->id => ['order' => 3, 'required' => false],
        ]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id]);
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $model->defaultValues()->attach($oldField->id, ['default_value' => 'Replaced']);
        $model->defaultValues()->attach($unsupportedField->id, ['default_value' => 'Preserved legacy value']);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->putJson(route('api.models.specs', $model), [
                'specs' => [$field->id => 'Configured value'],
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('models_custom_fields', [
            'asset_model_id' => $model->id,
            'custom_field_id' => $field->id,
            'default_value' => 'Configured value',
        ]);
        $this->assertDatabaseMissing('models_custom_fields', [
            'asset_model_id' => $model->id,
            'custom_field_id' => $oldField->id,
        ]);
        $this->assertDatabaseHas('models_custom_fields', [
            'asset_model_id' => $model->id,
            'custom_field_id' => $unsupportedField->id,
            'default_value' => 'Preserved legacy value',
        ]);
    }

    public function testCannotReplaceUnsupportedModelSpecifications()
    {
        $fieldset = CustomFieldset::factory()->create();
        $field = CustomField::factory()->create(['element' => 'text', 'format' => '', 'auto_add_to_fieldsets' => false]);
        $unsupportedField = CustomField::factory()->create([
            'element' => 'text',
            'format' => '',
            'is_unique' => true,
            'auto_add_to_fieldsets' => false,
        ]);
        $fieldset->fields()->attach($field->id, ['order' => 1, 'required' => false]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id]);
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $model->defaultValues()->attach($field->id, ['default_value' => 'Preserved']);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->putJson(route('api.models.specs', $model), [
                'specs' => [$unsupportedField->id => 'Rejected'],
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertJsonPath('messages.specs.0', 'Each specification must belong to the model category fieldset and support model-owned values.');

        $this->assertDatabaseHas('models_custom_fields', [
            'asset_model_id' => $model->id,
            'custom_field_id' => $field->id,
            'default_value' => 'Preserved',
        ]);
    }


    public function testRequiresPermissionToEditAssetModel()
    {
        $model = AssetModel::factory()->create();
        $this->actingAsForApi(User::factory()->create())
            ->patchJson(route('api.models.update', $model))
            ->assertForbidden();
    }

    public function testCanUpdateAssetModelViaPatch()
    {
        $model = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.models.update', $model), [
                'name' => 'Test Model',
                'category_id' => Category::factory()->forAssets()->create()->id,
                'obsolete' => true,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->assertStatus(200)
            ->json();

        $model->refresh();
        $this->assertEquals('Test Model', $model->name, 'Name was not updated');
        $this->assertTrue($model->obsolete, 'Obsolete was not updated');

    }

    public function testCannotUpdateAssetModelWithLegacyFieldsetOverrideWhileDisabled()
    {
        $model = AssetModel::factory()->create(['fieldset_id' => null]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.models.update', $model), [
                'name' => $model->name,
                'category_id' => $model->category_id,
                'custom_fieldset_id' => CustomFieldset::factory()->create()->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertJsonPath('messages.custom_fieldset_id.0', trans('admin/models/message.fieldset_override_disabled'));

        $this->assertNull($model->fresh()->fieldset_id);
    }

    public function testAfmCannotUpdateAssetModelOutsideManagedCategory()
    {
        $afm = User::factory()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $model = AssetModel::factory()->create();

        $this->actingAsForApi($afm)
            ->patchJson(route('api.models.update', $model), [
                'name' => 'Out Of Scope Update',
                'category_id' => $model->category_id,
            ])
            ->assertForbidden();

        $this->assertNotEquals('Out Of Scope Update', $model->fresh()->name);
    }

    public function testCannotUpdateAssetModelViaPatchWithAccessoryCategory()
    {
        $category = Category::factory()->forAccessories()->create();
        $model = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.models.update', $model), [
                'name' => 'Test Model',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertStatus(200)
            ->json();

        $category->refresh();
        $this->assertNotEquals('Test Model', $model->name, 'Name was not updated');
        $this->assertNotEquals('category_id', $category->id, 'Category ID was not updated');
    }

    public function testCannotUpdateAssetModelViaPatchWithLicenseCategory()
    {
        $category = Category::factory()->forLicenses()->create();
        $model = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.models.update', $model), [
                'name' => 'Test Model',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertStatus(200)
            ->json();

        $category->refresh();
        $this->assertNotEquals('Test Model', $model->name, 'Name was not updated');
        $this->assertNotEquals('category_id', $category->id, 'Category ID was not updated');
    }

    public function testCannotUpdateAssetModelViaPatchWithConsumableCategory()
    {
        $category = Category::factory()->forConsumables()->create();
        $model = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.models.update', $model), [
                'name' => 'Test Model',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertStatus(200)
            ->json();

        $category->refresh();
        $this->assertNotEquals('Test Model', $model->name, 'Name was not updated');
        $this->assertNotEquals('category_id', $category->id, 'Category ID was not updated');
    }

    public function testCannotUpdateAssetModelViaPatchWithComponentCategory()
    {
        $category = Category::factory()->forComponents()->create();
        $model = AssetModel::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->patchJson(route('api.models.update', $model), [
                'name' => 'Test Model',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertStatus(200)
            ->json();

        $category->refresh();
        $this->assertNotEquals('Test Model', $model->name, 'Name was not updated');
        $this->assertNotEquals('category_id', $category->id, 'Category ID was not updated');
    }

}
