<?php

namespace Tests\Feature\RegionalAssetCoordinators\Api;

use App\Models\Company;
use App\Models\Discipline;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use Tests\TestCase;

class IndexRegionalAssetCoordinatorsTest extends TestCase
{
    public function test_authentication_is_required(): void
    {
        User::factory()->create();

        $this->getJson(route('api.regional-asset-coordinators.index'))
            ->assertUnauthorized();
    }

    public function test_any_authenticated_user_can_view_active_coordinator_assignments(): void
    {
        $company = Company::factory()->create(['name' => 'Casablanca']);
        $discipline = Discipline::create(['name' => 'Electrical']);
        $coordinator = User::factory()->for($company)->create([
            'first_name' => 'Regional',
            'last_name' => 'Coordinator',
            'display_name' => 'Regional Coordinator',
            'email' => 'regional@example.com',
            'phone' => '+212500000000',
        ]);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.regional-asset-coordinators.index'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.coordinator', 'Regional Coordinator')
            ->assertJsonPath('rows.0.company', 'Casablanca')
            ->assertJsonPath('rows.0.discipline', 'Electrical')
            ->assertJsonPath('rows.0.email', 'regional@example.com')
            ->assertJsonPath('rows.0.phone', '+212500000000')
            ->assertJsonMissingPath('rows.0.username')
            ->assertJsonMissingPath('rows.0.permissions');
    }

    public function test_directory_is_cross_company_when_fmcs_is_enabled(): void
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        $discipline = Discipline::create(['name' => 'Power']);
        $viewer = User::factory()->for($companyA)->create();
        $coordinatorA = User::factory()->for($companyA)->create(['display_name' => 'Company A RAC']);
        $coordinatorB = User::factory()->for($companyB)->create(['display_name' => 'Company B RAC']);

        foreach ([$coordinatorA, $coordinatorB] as $coordinator) {
            RegionalAssetCoordinatorAssignment::create([
                'user_id' => $coordinator->id,
                'company_id' => $coordinator->company_id,
                'discipline_id' => $discipline->id,
            ]);
        }

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($viewer)
            ->getJson(route('api.regional-asset-coordinators.index'))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['coordinator' => 'Company A RAC'])
            ->assertJsonFragment(['coordinator' => 'Company B RAC']);
    }

    public function test_inactive_coordinators_are_not_listed(): void
    {
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Mechanical']);
        $coordinator = User::factory()->for($company)->create([
            'display_name' => 'Inactive RAC',
            'activated' => 0,
        ]);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.regional-asset-coordinators.index'))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('rows', []);
    }

    public function test_search_matches_company_and_discipline(): void
    {
        $company = Company::factory()->create(['name' => 'Rabat Site']);
        $discipline = Discipline::create(['name' => 'Automation']);
        $coordinator = User::factory()->for($company)->create(['display_name' => 'Rabat RAC']);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $viewer = User::factory()->create();

        $this->actingAsForApi($viewer)
            ->getJson(route('api.regional-asset-coordinators.index', ['search' => 'Rabat']))
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAsForApi($viewer)
            ->getJson(route('api.regional-asset-coordinators.index', ['search' => 'Electrical']))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
