<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class UpdateAssetModelsTest extends TestCase
{
    public function testPermissionRequiredToStoreAssetModel()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('models.update', ['model' => AssetModel::factory()->create()]), [
                'name' => 'Changed Name',
                'category_id' => Category::factory()->create()->id,
            ])
            ->assertForbidden();
    }

    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('models.edit', AssetModel::factory()->create()))
            ->assertOk();
    }

    public function testAfmCanEditManagedAssetModel()
    {
        $afm = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Managed Editable Model',
            'category_id' => $managedCategory->id,
        ]);

        $this->actingAs($afm)
            ->put(route('models.update', ['model' => $model]), [
                'name' => 'Managed Editable Model Updated',
                'category_id' => $managedCategory->id,
            ])
            ->assertRedirect(route('models.index'));

        $this->assertTrue(AssetModel::where('name', 'Managed Editable Model Updated')->exists());
    }

    public function testAfmCannotEditUnmanagedAssetModel()
    {
        $afm = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $unmanagedCategory = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $unmanagedCategory->id,
        ]);

        $this->actingAs($afm)
            ->get(route('models.edit', $model))
            ->assertForbidden();

        $response = $this->actingAs($afm)
            ->put(route('models.update', ['model' => $model]), [
                'name' => 'Should Not Update',
                'category_id' => $managedCategory->id,
            ]);

        $response->assertForbidden();
        $this->assertFalse(AssetModel::where('name', 'Should Not Update')->exists());
    }

    public function testAfmEditFormOnlyShowsManagedCategories()
    {
        $afm = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'name' => 'Managed Update Category',
            'manager_id' => $afm->id,
        ]);
        $otherManagedCategory = Category::factory()->forAssets()->create([
            'name' => 'Second Managed Update Category',
            'manager_id' => $afm->id,
        ]);
        $unmanagedCategory = Category::factory()->forAssets()->create([
            'name' => 'Unmanaged Update Category',
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => $managedCategory->id,
        ]);

        $response = $this->actingAs($afm)->get(route('models.edit', $model));

        $response->assertOk();
        $response->assertSee('Managed Update Category');
        $response->assertSee('Second Managed Update Category');
        $response->assertDontSee('Unmanaged Update Category');
    }

    public function testUserCanEditAssetModels()
    {
        $category = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create(['name' => 'Test Model', 'category_id' => $category->id]);
        $this->assertTrue(AssetModel::where('name', 'Test Model')->exists());

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $model]), [
                'name' => 'Test Model Edited',
                'category_id' => $model->category_id,
                'obsolete' => '1',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('models.index'));

        $this->followRedirects($response)->assertSee('Success');
        $this->assertTrue(AssetModel::where('name', 'Test Model Edited')->exists());
        $this->assertTrue(AssetModel::where('name', 'Test Model Edited')->sole()->obsolete);

    }

    public function testUserCanUpdateAssetModelReferencePrice()
    {
        $category = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
            'reference_price' => 100,
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $model]), [
                'name' => $model->name,
                'category_id' => $category->id,
                'reference_price' => '987.65',
            ])
            ->assertRedirect(route('models.index'));

        $this->assertEquals(987.65, (float) $model->fresh()->reference_price);
    }

    public function testUserCannotUpdateAssetModelWithNegativeReferencePrice()
    {
        $category = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
            'reference_price' => 100,
        ]);

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.edit', $model))
            ->put(route('models.update', ['model' => $model]), [
                'name' => $model->name,
                'category_id' => $category->id,
                'reference_price' => '-5',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('models.edit', $model));
        $response->assertSessionHasErrors(['reference_price']);
        $this->assertEquals(100.0, (float) $model->fresh()->reference_price);
    }

    public function testUserCannotChangeAssetModelCategoryType()
    {
        $category = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create(['name' => 'Test Model', 'category_id' => $category->id]);
        $this->assertTrue(AssetModel::where('name', 'Test Model')->exists());

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.edit', $model))
            ->put(route('models.update', $model), [
                'name' => 'Test Model Edited',
                'category_id' => Category::factory()->forAccessories()->create()->id,
            ])
            ->assertSessionHasErrors(['category_type'])
            ->assertInvalid(['category_type'])
            ->assertStatus(302)
            ->assertRedirect(route('models.edit', $model));

        $this->followRedirects($response)->assertSee(trans('general.error'));
        $this->assertFalse(AssetModel::where('name', 'Test Model Edited')->exists());

    }

    public function test_default_values_remain_unchanged_after_validation_error_occurs()
    {
        $this->markIncompleteIfMySQL('Custom Field Tests do not work in MySQL');

        $assetModel = AssetModel::factory()->create();
        $customFieldset = CustomFieldset::factory()->create();
        [$customFieldOne, $customFieldTwo] = CustomField::factory()->count(2)->create();

        $customFieldset->fields()->attach($customFieldOne, ['order' => 1, 'required' => false]);
        $customFieldset->fields()->attach($customFieldTwo, ['order' => 2, 'required' => false]);

        $assetModel->fieldset()->associate($customFieldset);

        $assetModel->defaultValues()->attach($customFieldOne, ['default_value' => 'first default value']);
        $assetModel->defaultValues()->attach($customFieldTwo, ['default_value' => 'second default value']);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $assetModel]), [
                // should trigger validation error without name, etc, and NOT remove or change default values
                'add_default_values' => '1',
                'fieldset_id' => $customFieldset->id,
                'default_values' => [
                    $customFieldOne->id => 'first changed value',
                    $customFieldTwo->id => 'second changed value',
                ],
            ]);

        $potentiallyChangedDefaultValues = $assetModel->defaultValues->pluck('pivot.default_value');
        $this->assertCount(2, $potentiallyChangedDefaultValues);
        $this->assertContains('first default value', $potentiallyChangedDefaultValues);
        $this->assertContains('second default value', $potentiallyChangedDefaultValues);
    }

    public function test_default_values_can_be_updated()
    {
        $this->markIncompleteIfMySQL('Custom Field Tests do not work in MySQL');

        $assetModel = AssetModel::factory()->create();
        $customFieldset = CustomFieldset::factory()->create();
        [$customFieldOne, $customFieldTwo] = CustomField::factory()->count(2)->create();

        $customFieldset->fields()->attach($customFieldOne, ['order' => 1, 'required' => false]);
        $customFieldset->fields()->attach($customFieldTwo, ['order' => 2, 'required' => false]);

        $assetModel->fieldset()->associate($customFieldset);

        $assetModel->defaultValues()->attach($customFieldOne, ['default_value' => 'first default value']);
        $assetModel->defaultValues()->attach($customFieldTwo, ['default_value' => 'second default value']);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $assetModel]), [
                // should trigger validation error without name, etc, and NOT remove or change default values
                'name' => 'Test Model Edited',
                'category_id' => $assetModel->category_id,
                'add_default_values' => '1',
                'fieldset_id' => $customFieldset->id,
                'default_values' => [
                    $customFieldOne->id => 'first changed value',
                    $customFieldTwo->id => 'second changed value',
                ],
            ]);

        $potentiallyChangedDefaultValues = $assetModel->defaultValues->pluck('pivot.default_value');
        $this->assertCount(2, $potentiallyChangedDefaultValues);
        $this->assertContains('first changed value', $potentiallyChangedDefaultValues);
        $this->assertContains('second changed value', $potentiallyChangedDefaultValues);
    }
}
