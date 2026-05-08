<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;
use Tests\TestCase;

class CreateAssetModelsTest extends TestCase
{
    public function testPermissionRequiredToCreateAssetModel()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('models.store'), [
                'name' => 'Test Model',
                'category_id' => Category::factory()->create()->id
            ])
            ->assertForbidden();
    }

    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('models.create'))
            ->assertOk();
    }

    public function testAfmCannotAccessCreatePageWithoutManagedCategories()
    {
        $afm = User::factory()->createAssetModels()->create();

        $this->actingAs($afm)
            ->get(route('models.create'))
            ->assertForbidden();
    }

    public function testAfmCreatePageOnlyShowsManagedCategories()
    {
        $afm = User::factory()->createAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'name' => 'Managed Alpha Category',
            'manager_id' => $afm->id,
        ]);
        $unmanagedCategory = Category::factory()->forAssets()->create([
            'name' => 'Unmanaged Beta Category',
        ]);

        $response = $this->actingAs($afm)->get(route('models.create'));

        $response->assertOk();
        $response->assertSee('Managed Alpha Category');
        $response->assertDontSee('Unmanaged Beta Category');
    }

    public function testUserCanCreateAssetModels()
    {
        $this->assertFalse(AssetModel::where('name', 'Test Model')->exists());

        $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.create'))
            ->post(route('models.store'), [
                'name' => 'Test Model',
                'category_id' => Category::factory()->create()->id,
                'obsolete' => '1',
            ])
            ->assertRedirect(route('models.index'));

        $this->assertTrue(AssetModel::where('name', 'Test Model')->exists());
        $this->assertTrue(AssetModel::where('name', 'Test Model')->sole()->obsolete);
    }

    public function testAfmCanCreateAssetModelInManagedCategory()
    {
        $afm = User::factory()->viewAssetModels()->createAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);

        $this->actingAs($afm)
            ->from(route('models.create'))
            ->post(route('models.store'), [
                'name' => 'Managed AFM Model',
                'category_id' => $managedCategory->id,
            ])
            ->assertRedirect(route('models.index'));

        $this->assertTrue(AssetModel::where('name', 'Managed AFM Model')->exists());
    }

    public function testAfmCannotCreateAssetModelInUnmanagedCategory()
    {
        $afm = User::factory()->viewAssetModels()->createAssetModels()->create();
        Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $unmanagedCategory = Category::factory()->forAssets()->create();

        $response = $this->actingAs($afm)
            ->from(route('models.create'))
            ->post(route('models.store'), [
                'name' => 'Unmanaged AFM Model',
                'category_id' => $unmanagedCategory->id,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('models.create'));
        $response->assertSessionHasErrors(['category_id']);
        $this->assertFalse(AssetModel::where('name', 'Unmanaged AFM Model')->exists());
    }

    public function testUserCannotUseAccessoryCategoryTypeAsAssetModelCategoryType()
    {

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.create'))
            ->post(route('models.store'), [
                'name' => 'Test Invalid Model Category',
                'category_id' => Category::factory()->forAccessories()->create()->id
            ]);
        $response->assertStatus(302);
        $response->assertRedirect(route('models.create'));
        $response->assertInvalid(['category_type']);
        $response->assertSessionHasErrors(['category_type']);
        $this->followRedirects($response)->assertSee(trans('general.error'));
        $this->assertFalse(AssetModel::where('name', 'Test Invalid Model Category')->exists());

    }

    public function testUniquenessAcrossModelNameAndModelNumber()
    {

        AssetModel::factory()->create(['name' => 'Test Model', 'model_number'=>'1234']);

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.create'))
            ->post(route('models.store'), [
                'name' => 'Test Model',
                'model_number' => '1234',
                'category_id' => Category::factory()->create()->id
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name','model_number'])
            ->assertRedirect(route('models.create'))
            ->assertInvalid(['name','model_number']);

        $this->followRedirects($response)->assertSee(trans('general.error'));

    }

    public function testUniquenessAcrossModelNameAndModelNumberWithoutModelNumber()
    {

        AssetModel::factory()->create(['name' => 'Test Model', 'model_number'=> null]);

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.create'))
            ->post(route('models.store'), [
                'name' => 'Test Model',
                'model_number' => null,
                'category_id' => Category::factory()->create()->id
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name'])
            ->assertRedirect(route('models.create'))
            ->assertInvalid(['name']);

        $this->followRedirects($response)->assertSee(trans('general.error'));

    }

}
