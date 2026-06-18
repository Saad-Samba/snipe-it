<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\Company;
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

    public function testAssetApiIndexReturnsServerBackedFilterOptionsFromFilteredDataset()
    {
        $pendingStatus = Statuslabel::factory()->create([
            'name' => 'Pending QA',
            'deployable' => 0,
            'pending' => 1,
            'archived' => 0,
        ]);

        $readyStatus = Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Ready QA',
        ]);

        $pendingModel = AssetModel::factory()->create(['name' => 'Pending Model']);
        $readyModel = AssetModel::factory()->create(['name' => 'Ready Model']);
        $pendingCompany = Company::factory()->create(['name' => 'Pending Company']);
        $readyCompany = Company::factory()->create(['name' => 'Ready Company']);
        $pendingLocation = Location::factory()->create(['name' => 'Pending Location']);

        Asset::factory()->create([
            'status_id' => $pendingStatus->id,
            'model_id' => $pendingModel->id,
            'company_id' => $pendingCompany->id,
            'location_id' => $pendingLocation->id,
        ]);

        Asset::factory()->create([
            'status_id' => $readyStatus->id,
            'model_id' => $readyModel->id,
            'company_id' => $readyCompany->id,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', [
                'status' => 'Pending',
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
            ]))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('filter_options.model.Pending Model', 'Pending Model')
                ->where('filter_options.company.Pending Company', 'Pending Company')
                ->where('filter_options.location.Pending Location', 'Pending Location')
                ->missing('filter_options.model.Ready Model')
                ->missing('filter_options.company.Ready Company')
                ->etc());
    }

    public function testAssetApiIndexCanFilterByMultipleSelectValuesWithinOneColumn()
    {
        $pendingStatus = Statuslabel::factory()->create([
            'name' => 'Pending QA',
            'deployable' => 0,
            'pending' => 1,
            'archived' => 0,
        ]);

        $readyStatus = Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Ready QA',
        ]);

        $otherStatus = Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Archived QA',
        ]);

        $pendingAsset = Asset::factory()->create([
            'name' => 'Pending asset',
            'status_id' => $pendingStatus->id,
        ]);

        $readyAsset = Asset::factory()->create([
            'name' => 'Ready asset',
            'status_id' => $readyStatus->id,
        ]);

        $excludedAsset = Asset::factory()->create([
            'name' => 'Excluded asset',
            'status_id' => $otherStatus->id,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', [
                'sort' => 'name',
                'order' => 'asc',
                'offset' => '0',
                'limit' => '20',
                'filter' => json_encode([
                    'status_label' => 'Pending QA,Ready QA',
                ]),
            ]))
            ->assertOk()
            ->assertResponseContainsInRows($pendingAsset, 'name')
            ->assertResponseContainsInRows($readyAsset, 'name')
            ->assertResponseDoesNotContainInRows($excludedAsset, 'name');
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

    public function testAssetApiIndexCanStackAssignedToAndCompanyFilterControls()
    {
        $companyA = Company::factory()->create(['name' => 'Filter Control Company A']);
        $companyB = Company::factory()->create(['name' => 'Filter Control Company B']);

        $assignee = User::factory()->create([
            'first_name' => 'Morgan',
            'last_name' => 'Filter',
            'username' => 'morgan.filter',
        ]);

        $matchingAsset = Asset::factory()->for($companyA)->create([
            'name' => 'Assigned asset in company A',
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
        ]);

        $wrongCompanyAsset = Asset::factory()->for($companyB)->create([
            'name' => 'Assigned asset in company B',
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
        ]);

        $wrongAssigneeAsset = Asset::factory()->for($companyA)->create([
            'name' => 'Unrelated assignee asset',
            'assigned_to' => User::factory()->create([
                'first_name' => 'Other',
                'last_name' => 'Person',
                'username' => 'other.person',
            ])->id,
            'assigned_type' => User::class,
        ]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.assets.index', [
                'filter' => json_encode([
                    'assigned_to' => $assignee->getFullNameAttribute(),
                    'company' => $companyA->name,
                ]),
            ]))
            ->assertOk()
            ->assertResponseContainsInRows($matchingAsset, 'name')
            ->assertResponseDoesNotContainInRows($wrongCompanyAsset, 'name')
            ->assertResponseDoesNotContainInRows($wrongAssigneeAsset, 'name');
    }

    public function testAssetQueryFiltersAssignedReadyToDeployAssetsByRawStatusLabelName()
    {
        $readyToDeployStatus = \App\Models\Statuslabel::factory()->readyToDeploy()->create([
            'name' => 'Ready to Deploy',
        ]);
        $otherStatus = \App\Models\Statuslabel::factory()->pending()->create([
            'name' => 'Pending Deployment',
        ]);
        $assignee = User::factory()->create();

        $matchingAsset = Asset::factory()->create([
            'name' => 'Assigned RTD asset',
            'status_id' => $readyToDeployStatus->id,
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
        ]);

        $wrongStatusAsset = Asset::factory()->create([
            'name' => 'Assigned pending asset',
            'status_id' => $otherStatus->id,
            'assigned_to' => $assignee->id,
            'assigned_type' => User::class,
        ]);

        $matchingIds = Asset::query()
            ->byFilter([
                'status_label' => $readyToDeployStatus->name,
            ])
            ->pluck('assets.id');

        $this->assertTrue($matchingIds->contains($matchingAsset->id));
        $this->assertFalse($matchingIds->contains($wrongStatusAsset->id));
    }
}
