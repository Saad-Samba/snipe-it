<?php

namespace Tests\Feature\AssetModels\Api;

use App\Models\Asset;
use App\Models\Company;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Project;
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

    public function testAssetModelIndexReturnsReferencePriceFields()
    {
        $model = AssetModel::factory()->create([
            'name' => 'Priced API Model',
            'reference_price' => 1499.99,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.models.index', [
                    'search' => 'Priced API Model',
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn(AssertableJson $json) => $json
                ->where('rows.0.id', $model->id)
                ->where('rows.0.reference_price', 1499.99)
                ->where('rows.0.reference_price_formatted', '1,499.99')
                ->etc());
    }

    public function testAfmAssetModelIndexOnlyReturnsManagedCategoryModels()
    {
        $afm = User::factory()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $managedModel = AssetModel::factory()->create([
            'name' => 'Managed API Model',
            'category_id' => $managedCategory->id,
        ]);
        AssetModel::factory()->create([
            'name' => 'Unmanaged API Model',
        ]);

        $this->actingAsForApi($afm)
            ->getJson(
                route('api.models.index', [
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJson(fn(AssertableJson $json) => $json
                ->where('total', 1)
                ->where('rows.0.name', $managedModel->name)
                ->etc());
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

    public function testAssetModelIndexCanFilterByObsoleteState()
    {
        $obsoleteModel = AssetModel::factory()->create([
            'name' => 'Obsolete model',
            'obsolete' => true,
        ]);

        $activeModel = AssetModel::factory()->create([
            'name' => 'Active model',
            'obsolete' => false,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', ['obsolete' => 1]))
            ->assertOk()
            ->assertResponseContainsInRows($obsoleteModel, 'name')
            ->assertResponseDoesNotContainInRows($activeModel, 'name');

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', ['obsolete' => 0]))
            ->assertOk()
            ->assertResponseContainsInRows($activeModel, 'name')
            ->assertResponseDoesNotContainInRows($obsoleteModel, 'name');
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
            'model_id' => $availableModel->id,
            'status_id' => Statuslabel::factory()->pending()->create()->id,
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
                ->where('rows.0.remaining', 1)
                ->missing('rows.1')
                ->etc());
    }

    public function testAssetModelIndexExposesRequestActionsForAvailableModels()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $requester->id,
        ]);
        $availableModel = AssetModel::factory()->create([
            'name' => 'Requestable Available Model',
            'category_id' => $managedCategory->id,
        ]);
        $unavailableModel = AssetModel::factory()->create([
            'name' => 'Unavailable Model',
            'category_id' => $managedCategory->id,
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

        $this->actingAsForApi($requester)
            ->getJson(route('api.models.index', [
                'search' => 'Requestable Available Model',
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.available_actions.request', true)
                ->where('rows.0.available_actions.cancel_request', false)
                ->where('rows.0.available_actions.update_request', false)
                ->where('rows.0.requested_quantity', null)
                ->etc());

        $availableModel->request(1);

        $this->actingAsForApi($requester)
            ->getJson(route('api.models.index', [
                'search' => 'Requestable Available Model',
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.available_actions.request', true)
                ->where('rows.0.available_actions.cancel_request', false)
                ->where('rows.0.available_actions.update_request', false)
                ->where('rows.0.requested_quantity', 1)
                ->etc());
    }

    public function testAssetModelIndexKeepsRequestActionWhenReusableStockIsUnavailable()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $requester->id,
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Requestable Without Stock',
            'category_id' => $managedCategory->id,
        ]);

        $deployableStatus = Statuslabel::factory()->rtd()->create();
        $assignedUser = User::factory()->create();

        Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'assigned_to' => $assignedUser->id,
            'assigned_type' => User::class,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.models.index', [
                'search' => 'Requestable Without Stock',
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.available_actions.request', true)
                ->where('rows.0.available_actions.cancel_request', false)
                ->where('rows.0.available_actions.update_request', false)
                ->where('rows.0.requested_quantity', null)
                ->etc());
    }

    public function testAssetModelIndexDoesNotExposeSingularRequestMetadataWhenMultipleActiveRequestsExist()
    {
        $requester = User::factory()->requestAssetModels()->viewAssetModels()->create();
        $managedCategory = Category::factory()->forAssets()->create([
            'manager_id' => $requester->id,
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Requestable Multiple Requests Model',
            'category_id' => $managedCategory->id,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'company_id' => Company::factory()->create()->id,
            'project_id' => Project::factory()->create()->id,
            'quantity' => 1,
        ]);

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'company_id' => Company::factory()->create()->id,
            'project_id' => Project::factory()->create()->id,
            'quantity' => 2,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.models.index', [
                'search' => 'Requestable Multiple Requests Model',
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.available_actions.request', true)
                ->where('rows.0.requested_quantity', null)
                ->where('rows.0.requested_company_id', null)
                ->where('rows.0.requested_project_id', null)
                ->where('rows.0.requested_needed_by_date', null)
                ->etc());
    }

    public function testAssetModelIndexSortsByCategoryFieldsetAndIgnoresStoredModelOverride()
    {
        $alphaFieldset = CustomFieldset::factory()->create(['name' => 'Alpha Fieldset']);
        $zuluFieldset = CustomFieldset::factory()->create(['name' => 'Zulu Fieldset']);
        $storedOverride = CustomFieldset::factory()->create(['name' => 'Aardvark Stored Override']);

        $alphaCategory = Category::factory()->forAssets()->create([
            'fieldset_id' => $alphaFieldset->id,
        ]);
        $zuluCategory = Category::factory()->forAssets()->create([
            'fieldset_id' => $zuluFieldset->id,
        ]);

        AssetModel::factory()->create([
            'category_id' => $zuluCategory->id,
            'fieldset_id' => $storedOverride->id,
            'name' => 'Category-only model',
        ]);

        AssetModel::factory()->create([
            'category_id' => $alphaCategory->id,
            'fieldset_id' => null,
            'name' => 'Inherited sort model',
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
                ->where('rows.1.name', 'Category-only model')
                ->where('rows.1.fieldset.name', 'Zulu Fieldset')
                ->etc());
    }

    public function testAssetModelIndexPreservesInventoryCountsWhenSortingByCreator()
    {
        $creator = User::factory()->create([
            'first_name' => 'Model',
            'last_name' => 'Creator',
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'Creator-sorted inventory model',
            'created_by' => $creator->id,
        ]);
        $ready = Statuslabel::factory()->readyToDeploy()->create();

        Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $ready->id,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', [
                'name' => $model->name,
                'sort' => 'created_by',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
            ->assertOk()
            ->assertJsonPath('rows.0.id', $model->id)
            ->assertJsonPath('rows.0.assets_count', 1)
            ->assertJsonPath('rows.0.remaining', 1)
            ->assertJsonPath('rows.0.assets_assigned_count', 0)
            ->assertJsonPath('rows.0.assets_archived_count', 0);
    }

}
