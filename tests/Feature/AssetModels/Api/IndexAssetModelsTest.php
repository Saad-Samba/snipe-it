<?php

namespace Tests\Feature\AssetModels\Api;

use App\Models\Asset;
use App\Models\Company;
use App\Models\AssetModel;
use App\Models\Statuslabel;
use App\Models\User;
use App\Models\Category;
use App\Models\CustomFieldset;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class IndexAssetModelsTest extends TestCase
{
    public function testViewingAssetModelIndexRequiresAuthentication()
    {
        $this->getJson(route('api.models.index'))->assertRedirect();
    }

    public function testViewingAssetModelIndexRequiresPermission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.models.index'))
            ->assertForbidden();
    }

    public function testAssetModelIndexReturnsExpectedAssetModels()
    {
        AssetModel::factory()->count(3)->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetModelIndexReturnsObsoleteFlag()
    {
        AssetModel::factory()->create([
            'name' => 'Obsolete model',
            'obsolete' => true,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'search' => 'Obsolete model',
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn(AssertableJson $json) => $json
                ->where('rows.0.name', 'Obsolete model')
                ->where('rows.0.obsolete', true)
                ->etc());
    }

    public function testAssetModelIndexSearchReturnsExpectedAssetModels()
    {
        AssetModel::factory()->count(3)->create();
        AssetModel::factory()->count(1)->create(['name' => 'Test Model']);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'search' => 'Test Model',
                    'sort' => 'id',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 1)->etc());
    }

    public function testAssetModelIndexReturnsInheritedCategoryFieldset()
    {
        $fieldset = CustomFieldset::factory()->create();
        $category = Category::factory()->forAssets()->create([
            'fieldset_id' => $fieldset->id,
        ]);

        AssetModel::factory()->create([
            'category_id' => $category->id,
            'fieldset_id' => null,
            'name' => 'Inherited Fieldset Model',
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'search' => 'Inherited Fieldset Model',
                    'sort' => 'id',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.fieldset.id', $fieldset->id)
                ->where('rows.0.fieldset.name', $fieldset->name)
                ->etc());
    }

    public function testAssetModelIndexCanFilterToAvailableModelsWithinCategory()
    {
        $category = Category::factory()->forAssets()->create();
        $availableModel = AssetModel::factory()->create([
            'category_id' => $category->id,
            'name' => 'Available Model',
        ]);
        $unavailableModel = AssetModel::factory()->create([
            'category_id' => $category->id,
            'name' => 'Unavailable Model',
        ]);

        $deployableStatus = Statuslabel::factory()->rtd()->create();
        $assignedUser = User::factory()->create();

        Asset::factory()->create([
            'model_id' => $availableModel->id,
            'status_id' => $deployableStatus->id,
        ]);

        Asset::factory()->create([
            'model_id' => $unavailableModel->id,
            'status_id' => $deployableStatus->id,
            'assigned_to' => $assignedUser->id,
            'assigned_type' => User::class,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'category_id' => $category->id,
                    'available_models' => 1,
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.name', 'Available Model')
                ->missing('rows.1')
                ->etc());
    }

    public function testAssetModelIndexSortsByInheritedCategoryFieldsetName()
    {
        $inheritedFieldset = CustomFieldset::factory()->create(['name' => 'Alpha Fieldset']);
        $explicitFieldset = CustomFieldset::factory()->create(['name' => 'Zulu Fieldset']);

        $category = Category::factory()->forAssets()->create([
            'fieldset_id' => $inheritedFieldset->id,
        ]);

        AssetModel::factory()->create([
            'category_id' => $category->id,
            'fieldset_id' => null,
            'name' => 'Inherited sort model',
        ]);

        AssetModel::factory()->create([
            'category_id' => $category->id,
            'fieldset_id' => $explicitFieldset->id,
            'name' => 'Explicit sort model',
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'sort' => 'fieldset',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.name', 'Inherited sort model')
                ->where('rows.1.name', 'Explicit sort model')
                ->etc());
    }

}
