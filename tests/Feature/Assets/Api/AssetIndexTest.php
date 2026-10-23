<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Statuslabel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AssetIndexTest extends TestCase
{
    public function testAssetApiIndexReturnsExpectedAssets()
    {
        Asset::factory()->count(3)->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.assets.index', [
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

    public function testAssetApiIndexCanFilterReusableAssetsWithinCategory()
    {
        $category = Category::factory()->assetLaptopCategory()->create();
        $model = AssetModel::factory()->create(['category_id' => $category->id]);
        $deployableStatus = Statuslabel::factory()->create(['deployable' => 1, 'archived' => 0]);
        $undeployableStatus = Statuslabel::factory()->create(['deployable' => 0, 'archived' => 0]);
        $archivedStatus = Statuslabel::factory()->create(['deployable' => 0, 'archived' => 1]);
        $pendingStatus = Statuslabel::factory()->create(['deployable' => 1, 'pending' => 1, 'archived' => 0]);

        $reusableAsset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
        ]);

        $undeployableAsset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $undeployableStatus->id,
        ]);

        $archivedAsset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $archivedStatus->id,
        ]);

        $assignedDeployableAsset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $deployableStatus->id,
            'assigned_to' => User::factory()->create()->id,
            'assigned_type' => User::class,
        ]);

        $pendingAsset = Asset::factory()->create([
            'model_id' => $model->id,
            'status_id' => $pendingStatus->id,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', [
                'category_id' => $category->id,
                'reusable_assets' => 1,
            ]))
            ->assertOk()
            ->assertResponseContainsInRows($reusableAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($assignedDeployableAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($pendingAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($undeployableAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($archivedAsset, 'asset_tag');
    }

    public function testAssetApiIndexReturnsModelObsoleteFlagAndCanFilterByIt()
    {
        $obsoleteAsset = Asset::factory()->create([
            'name' => 'Obsolete asset',
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => true])->id,
        ]);

        $activeAsset = Asset::factory()->create([
            'name' => 'Active asset',
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => false])->id,
        ]);

        $user = User::factory()->superuser()->create();

        $this->actingAsForApi($user)
            ->getJson(route('api.assets.index', ['search' => 'Obsolete asset']))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('rows.0.model.name', $obsoleteAsset->model->name)
                ->where('rows.0.model.obsolete', true)
                ->etc());

        $this->actingAsForApi($user)
            ->getJson(route('api.assets.index', ['model_obsolete' => 1]))
            ->assertOk()
            ->assertResponseContainsInRows($obsoleteAsset, 'name')
            ->assertResponseDoesNotContainInRows($activeAsset, 'name');

        $this->actingAsForApi($user)
            ->getJson(route('api.assets.index', ['model_obsolete' => 0]))
            ->assertOk()
            ->assertResponseContainsInRows($activeAsset, 'name')
            ->assertResponseDoesNotContainInRows($obsoleteAsset, 'name');
    }

    public function testAssetApiIndexCanStackStatusAssignmentAndModelObsoleteFilters()
    {
        $status = \App\Models\Statuslabel::factory()->readyToDeploy()->create();
        $user = User::factory()->superuser()->create();
        $assignee = User::factory()->create();

        $matchingAsset = Asset::factory()->create([
            'name' => 'Assigned obsolete matching asset',
            'status_id' => $status->id,
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => true])->id,
        ]);

        $wrongAssignment = Asset::factory()->create([
            'name' => 'Unassigned obsolete asset',
            'status_id' => $status->id,
            'assigned_to' => null,
            'assigned_type' => null,
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => true])->id,
        ]);

        $wrongObsolete = Asset::factory()->create([
            'name' => 'Assigned current asset',
            'status_id' => $status->id,
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => false])->id,
        ]);

        $wrongStatus = Asset::factory()->create([
            'name' => 'Assigned obsolete wrong status asset',
            'status_id' => \App\Models\Statuslabel::factory()->readyToDeploy()->create()->id,
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
            'model_id' => \App\Models\AssetModel::factory()->create(['obsolete' => true])->id,
        ]);

        $this->actingAsForApi($user)
            ->getJson(route('api.assets.index', [
                'status_id' => $status->id,
                'assignment' => 'assigned',
                'model_obsolete' => 1,
            ]))
            ->assertOk()
            ->assertResponseContainsInRows($matchingAsset, 'name')
            ->assertResponseDoesNotContainInRows($wrongAssignment, 'name')
            ->assertResponseDoesNotContainInRows($wrongObsolete, 'name')
            ->assertResponseDoesNotContainInRows($wrongStatus, 'name');
    }

    public function testAssetApiIndexReturnsDisplayUpcomingAuditsDue()
    {
        Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->format('Y-m-d')]);


        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.assets.list-upcoming', ['action' => 'audits', 'upcoming_status' => 'due']))
                ->assertOk()
                ->assertJsonStructure([
                    'total',
                    'rows',
                ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexReturnsOverdueForAudit()
    {
        Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->subDays(1)->format('Y-m-d')]);
        
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.assets.list-upcoming', ['action' => 'audits', 'upcoming_status' => 'overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }


    public function testAssetApiIndexReturnsDueOrOverdueForAudit()
    {
        Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->format('Y-m-d')]);
        Asset::factory()->count(2)->create(['next_audit_date' => Carbon::now()->subDays(1)->format('Y-m-d')]);
        
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.assets.list-upcoming', ['action' => 'audits', 'upcoming_status' => 'due-or-overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 5)->etc());
    }



    public function testAssetApiIndexReturnsDueForExpectedCheckin()
    {
        Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->format('Y-m-d')]);
        
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'due'])
            )
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexReturnsOverdueForExpectedCheckin()
    {
        Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->subDays(1)->format('Y-m-d')]);
        
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexReturnsDueOrOverdueForExpectedCheckin()
    {
        Asset::factory()->count(3)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->subDays(1)->format('Y-m-d')]);
        Asset::factory()->count(2)->create(['assigned_to' => '1', 'assigned_type' => User::class, 'expected_checkin' => Carbon::now()->format('Y-m-d')]);
        
        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.list-upcoming', ['action' => 'checkins', 'upcoming_status' => 'due-or-overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 5)->etc());
    }

    public function testAssetApiIndexAdheresToCompanyScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $assetA = Asset::factory()->for($companyA)->create();
        $assetB = Asset::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->viewAssets()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->viewAssets()->make());

        $this->settings->disableMultipleFullCompanySupport();

        $this->actingAsForApi($superUser)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyB)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($superUser)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseDoesNotContainInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyB)
            ->getJson(route('api.assets.index'))
            ->assertResponseDoesNotContainInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');
    }

    public function testAssetFamilyManagerSeesOnlyAssetsInManagedCategoriesAcrossCompanies()
    {
        $this->settings->disableMultipleFullCompanySupport();

        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $afm = User::factory()->create(['company_id' => null]);

        $managedCategory = Category::factory()->assetLaptopCategory()->create([
            'manager_id' => $afm->id,
        ]);
        $unmanagedCategory = Category::factory()->assetLaptopCategory()->create();

        $managedModel = AssetModel::factory()->create(['category_id' => $managedCategory->id]);
        $unmanagedModel = AssetModel::factory()->create(['category_id' => $unmanagedCategory->id]);

        $createAsset = fn (Company $company, AssetModel $model) => Asset::withoutEvents(
            fn () => Asset::forceCreate(Asset::factory()->raw([
                'company_id' => $company->id,
                'model_id' => $model->id,
            ]))
        );

        $managedAssetA = $createAsset($companyA, $managedModel);
        $managedAssetB = $createAsset($companyB, $managedModel);
        $unmanagedAsset = $createAsset($companyA, $unmanagedModel);

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($afm)
            ->getJson(route('api.assets.index'))
            ->assertOk()
            ->assertResponseContainsInRows($managedAssetA, 'asset_tag')
            ->assertResponseContainsInRows($managedAssetB, 'asset_tag')
            ->assertResponseDoesNotContainInRows($unmanagedAsset, 'asset_tag');
    }

    public function testAssetApiIndexFiltersByOwnerId()
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $ownedAsset = Asset::factory()->create(['owner_id' => $ownerA->id]);
        $otherOwnedAsset = Asset::factory()->create(['owner_id' => $ownerB->id]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', ['owner_id' => $ownerA->id]))
            ->assertOk()
            ->assertResponseContainsInRows($ownedAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($otherOwnedAsset, 'asset_tag');
    }

    public function testAssetApiIndexSeparatesOwnedFromAssignedFilters()
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedOnlyAsset = Asset::factory()->create([
            'owner_id' => $owner->id,
            'assigned_to' => null,
            'assigned_type' => null,
        ]);

        $assignedOnlyAsset = Asset::factory()->create([
            'owner_id' => $otherUser->id,
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
        ]);

        $super = User::factory()->superuser()->create();

        $this->actingAsForApi($super)
            ->getJson(route('api.assets.index', ['owner_id' => $owner->id]))
            ->assertOk()
            ->assertResponseContainsInRows($ownedOnlyAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($assignedOnlyAsset, 'asset_tag');

        $this->actingAsForApi($super)
            ->getJson(route('api.assets.index', [
                'assigned_to' => $assignee->id,
                'assigned_type' => User::class,
            ]))
            ->assertOk()
            ->assertResponseContainsInRows($assignedOnlyAsset, 'asset_tag')
            ->assertResponseDoesNotContainInRows($ownedOnlyAsset, 'asset_tag');
    }
}
