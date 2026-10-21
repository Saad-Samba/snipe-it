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
use App\Models\CustomField;
use App\Models\CustomFieldset;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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

    public function testAssetModelIndexIncludesModelSpecificationsButExcludesUniqueAndEncryptedFields()
    {
        $fieldset = CustomFieldset::factory()->create();
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id]);
        $voltage = CustomField::factory()->create(['name' => 'Output Voltage']);
        $offset = CustomField::factory()->create(['name' => 'Voltage Offset']);
        $serial = CustomField::factory()->create(['name' => 'Serial Number', 'is_unique' => true]);
        $secret = CustomField::factory()->create(['name' => 'Internal Secret', 'field_encrypted' => true]);
        $fieldset->fields()->attach([
            $voltage->id => ['order' => 1, 'required' => false],
            $serial->id => ['order' => 2, 'required' => false],
            $secret->id => ['order' => 3, 'required' => false],
            $offset->id => ['order' => 4, 'required' => false],
        ]);
        $model = AssetModel::factory()->create([
            'name' => 'PSU Model',
            'category_id' => $category->id,
        ]);
        $model->defaultValues()->attach([
            $voltage->id => ['default_value' => '24 V'],
            $serial->id => ['default_value' => 'SN-123'],
            $secret->id => ['default_value' => 'hidden'],
            $offset->id => ['default_value' => '0'],
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', ['search' => 'PSU Model']))
            ->assertOk()
            ->assertJsonCount(2, 'rows.0.default_fieldset_values')
            ->assertJsonPath('rows.0.default_fieldset_values.0.name', 'Output Voltage')
            ->assertJsonPath('rows.0.default_fieldset_values.0.default_value', '24 V')
            ->assertJsonPath('rows.0.specification_'.$voltage->id, '24 V')
            ->assertJsonPath('rows.0.default_fieldset_values.1.name', 'Voltage Offset')
            ->assertJsonPath('rows.0.default_fieldset_values.1.default_value', '0')
            ->assertJsonPath('rows.0.specification_'.$offset->id, '0')
            ->assertJsonMissingPath('rows.0.default_fieldset_values.2');
    }

    public function testAssetModelSpecificationsUseTheCategoryFieldsetWhenModelOverridesAreEnabled()
    {
        $categoryFieldset = CustomFieldset::factory()->create(['name' => 'Category Specifications']);
        $overrideFieldset = CustomFieldset::factory()->create(['name' => 'Model Override']);
        $categoryField = CustomField::factory()->create(['name' => 'Output Voltage']);
        $overrideField = CustomField::factory()->create(['name' => 'Override-only field']);
        $categoryFieldset->fields()->attach($categoryField->id, ['order' => 1, 'required' => false]);
        $overrideFieldset->fields()->attach($overrideField->id, ['order' => 1, 'required' => false]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $categoryFieldset->id]);
        $model = AssetModel::factory()->create([
            'name' => 'Overridden PSU Model',
            'category_id' => $category->id,
            'fieldset_id' => $overrideFieldset->id,
        ]);
        $model->defaultValues()->attach([
            $categoryField->id => ['default_value' => '24 V'],
            $overrideField->id => ['default_value' => 'Model-only value'],
        ]);

        config()->set('leams.model_fieldset_overrides', true);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', ['search' => 'Overridden PSU Model']))
            ->assertOk()
            ->assertJsonCount(1, 'rows.0.default_fieldset_values')
            ->assertJsonPath('rows.0.default_fieldset_values.0.name', 'Output Voltage')
            ->assertJsonPath('rows.0.specification_'.$categoryField->id, '24 V')
            ->assertJsonMissingPath('rows.0.specification_'.$overrideField->id);
    }

    public function testAssetModelIndexOnlyLoadsSpecificationValuesForTheCurrentPage()
    {
        $fieldset = CustomFieldset::factory()->create();
        $field = CustomField::factory()->create(['name' => 'Page-scoped Voltage']);
        $missingField = CustomField::factory()->create(['name' => 'Page-scoped Connector']);
        $fieldset->fields()->attach([
            $field->id => ['order' => 1, 'required' => false],
            $missingField->id => ['order' => 2, 'required' => false],
        ]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id]);
        $firstModel = AssetModel::factory()->create(['name' => 'AAA Page Model', 'category_id' => $category->id]);
        $offPageModel = AssetModel::factory()->create(['name' => 'ZZZ Page Model', 'category_id' => $category->id]);
        $firstModel->defaultValues()->attach($field->id, ['default_value' => '0']);
        $firstModel->defaultValues()->attach($field->id, ['default_value' => '48 V']);
        $offPageModel->defaultValues()->attach($field->id, ['default_value' => '24 V']);

        $specificationQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$specificationQueries) {
            if (str_contains(strtolower($query->sql), 'models_custom_fields')) {
                $specificationQueries[] = $query;
            }
        });

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', ['sort' => 'name', 'order' => 'asc', 'limit' => '1']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.id', $firstModel->id)
            ->assertJsonPath('rows.0.specification_'.$field->id, '0')
            ->assertJsonPath('rows.0.specification_'.$missingField->id, null);

        $this->assertCount(1, $specificationQueries);
        $this->assertContains($firstModel->id, $specificationQueries[0]->bindings);
        $this->assertNotContains($offPageModel->id, $specificationQueries[0]->bindings);
    }

    public function testAssetModelIndexFiltersCategorySpecificationsBeforePagination()
    {
        $fieldset = CustomFieldset::factory()->create(['name' => 'Power Specifications']);
        $power = CustomField::factory()->create(['name' => 'Power Output', 'format' => 'NUMERIC']);
        $connector = CustomField::factory()->create([
            'name' => 'Connector',
            'element' => 'listbox',
            'field_values' => "USB-C|USB-C connector\nEthernet",
        ]);
        $fieldset->fields()->attach([
            $power->id => ['order' => 1, 'required' => false],
            $connector->id => ['order' => 2, 'required' => false],
        ]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id]);

        $matchingModel = AssetModel::factory()->create([
            'name' => 'Power Adapter USB-C',
            'category_id' => $category->id,
        ]);
        $wrongConnectorModel = AssetModel::factory()->create([
            'name' => 'Power Adapter Ethernet',
            'category_id' => $category->id,
        ]);
        $lowPowerModel = AssetModel::factory()->create([
            'name' => 'Power Adapter Low Output',
            'category_id' => $category->id,
        ]);
        $differentNameModel = AssetModel::factory()->create([
            'name' => 'Travel Charger',
            'category_id' => $category->id,
        ]);

        $matchingModel->defaultValues()->attach([
            $power->id => ['default_value' => '24'],
            $connector->id => ['default_value' => 'USB-C'],
        ]);
        $wrongConnectorModel->defaultValues()->attach([
            $power->id => ['default_value' => '30'],
            $connector->id => ['default_value' => 'Ethernet'],
        ]);
        $lowPowerModel->defaultValues()->attach([
            $power->id => ['default_value' => '12'],
            $connector->id => ['default_value' => 'USB-C'],
        ]);
        $differentNameModel->defaultValues()->attach([
            $power->id => ['default_value' => '24'],
            $connector->id => ['default_value' => 'USB-C'],
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', [
                'category_id' => $category->id,
                'search' => 'Power Adapter',
                'specification_filters' => json_encode([
                    ['field_id' => $power->id, 'operator' => '>=', 'value' => '24'],
                    ['field_id' => $connector->id, 'operator' => ':', 'value' => 'USB-C'],
                ]),
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $matchingModel->id)
            ->assertJsonPath('rows.0.specification_'.$power->id, '24')
            ->assertJsonPath('rows.0.specification_'.$connector->id, 'USB-C');

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', [
                'category_id' => $category->id,
                'specification_filters' => json_encode([
                    ['field_id' => $connector->id, 'operator' => ':', 'value' => 'USB'],
                ]),
            ]))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function testAssetModelIndexRejectsSpecificationFieldsOutsideTheSelectedCategory()
    {
        $categoryFieldset = CustomFieldset::factory()->create();
        $otherFieldset = CustomFieldset::factory()->create();
        $otherField = CustomField::factory()->create(['name' => 'Other Category Field']);
        $otherFieldset->fields()->attach($otherField->id, ['order' => 1, 'required' => false]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $categoryFieldset->id]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.models.index', [
                'category_id' => $category->id,
                'specification_filters' => json_encode([
                    ['field_id' => $otherField->id, 'operator' => ':', 'value' => 'value'],
                ]),
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('messages.specification_filters.0', 'Each field must be a supported specification for the selected category.');
    }

    public function testCheckboxSpecificationFiltersMatchIndividualSelections()
    {
        $fieldset = CustomFieldset::factory()->create();
        $features = CustomField::factory()->create([
            'name' => 'Features',
            'element' => 'checkbox',
            'field_values' => "Blue\nBlue%\nRed",
        ]);
        $fieldset->fields()->attach($features->id, ['order' => 1, 'required' => false]);
        $category = Category::factory()->forAssets()->create(['fieldset_id' => $fieldset->id]);

        $multiSelection = AssetModel::factory()->create(['category_id' => $category->id]);
        $multiSelection->defaultValues()->attach($features->id, ['default_value' => 'Blue, Red']);
        $percentSelection = AssetModel::factory()->create(['category_id' => $category->id]);
        $percentSelection->defaultValues()->attach($features->id, ['default_value' => 'Blue%']);
        $redSelection = AssetModel::factory()->create(['category_id' => $category->id]);
        $redSelection->defaultValues()->attach($features->id, ['default_value' => 'Red']);

        $this->actingAsForApi(User::factory()->superuser()->create());

        foreach ([
            ['Blue', ':', [$multiSelection->id]],
            ['Blue%', ':', [$percentSelection->id]],
            ['Red', '=', [$multiSelection->id, $redSelection->id]],
            ['Blu', ':', []],
        ] as [$value, $operator, $expectedIds]) {
            $response = $this->getJson(route('api.models.index', [
                'category_id' => $category->id,
                'specification_filters' => json_encode([
                    ['field_id' => $features->id, 'operator' => $operator, 'value' => $value],
                ]),
            ]))->assertOk()->assertJsonPath('total', count($expectedIds));

            $this->assertEqualsCanonicalizing($expectedIds, collect($response->json('rows'))->pluck('id')->all());
        }
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

    public function testAssetModelIndexLoadsCurrentUsersRequestsInOneQuery()
    {
        $requester = User::factory()->superuser()->create();
        $models = AssetModel::factory()->count(3)->create();
        $requestedModel = $models->first();

        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $requestedModel->id,
            'requestable_type' => AssetModel::class,
            'quantity' => 2,
        ]);

        $checkoutRequestQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$checkoutRequestQueries) {
            if (str_contains(strtolower($query->sql), 'checkout_requests')) {
                $checkoutRequestQueries[] = $query->sql;
            }
        });

        $response = $this->actingAsForApi($requester)
            ->getJson(route('api.models.index', ['limit' => '20']))
            ->assertOk();

        $this->assertCount(1, $checkoutRequestQueries);

        $rows = collect($response->json('rows'))->keyBy('id');

        $this->assertSame(2, $rows->get($requestedModel->id)['requested_quantity']);
        $this->assertNull($rows->get($models->last()->id)['requested_quantity']);
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
