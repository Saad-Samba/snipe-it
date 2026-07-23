<?php

namespace Tests\Feature\Requests;

use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use App\Notifications\RacScopedRequestSummaryNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseRequestWorkflowTest extends TestCase
{
    public function test_license_request_estimate_counts_available_and_expected_release_seats(): void
    {
        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Digital Engineering', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'reassignable' => true,
            'purchase_cost' => 125.50,
            'seats' => 3,
        ])->fresh();

        $futureUser = User::factory()->create();
        $laterUser = User::factory()->create();
        $seats = $license->licenseSeats()->orderBy('id')->get();
        $seats[1]->forceFill([
            'assigned_to' => $futureUser->id,
            'expected_release_date' => '2026-06-10',
        ])->save();
        $seats[2]->forceFill([
            'assigned_to' => $laterUser->id,
            'expected_release_date' => '2026-07-10',
        ])->save();

        $this->actingAs($requester)
            ->postJson(route('account.request-estimate', ['itemType' => 'license', 'itemId' => $license->id]), [
                'request-quantity' => 3,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-15',
                'requested_for_type' => 'user',
                'requested_for_display' => 'Future assignee',
            ])
            ->assertOk()
            ->assertJsonPath('requested_quantity', 3)
            ->assertJsonPath('reusable_now', 1)
            ->assertJsonPath('reusable_quantity', 1)
            ->assertJsonPath('expected_release_before_needed_by_quantity', 1)
            ->assertJsonPath('potentially_coverable_quantity', 2)
            ->assertJsonPath('procurement_shortfall', 1)
            ->assertJsonPath('reference_price_snapshot', 125.5);
    }

    public function test_license_request_persists_scope_assignee_and_notifies_candidate_racs(): void
    {
        Notification::fake();

        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $discipline = Discipline::create(['name' => 'Software', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create(['first_name' => 'License', 'last_name' => 'RAC']);
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $project = Project::factory()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'project_id' => null,
            'reassignable' => true,
            'perpetual' => true,
            'expiration_date' => null,
            'termination_date' => null,
            'purchase_cost' => 499.99,
            'seats' => 3,
        ])->fresh();

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'license', 'itemId' => $license->id]), [
                'request-quantity' => 2,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
                'requested_for_type' => 'user',
                'requested_for_display' => 'Jane Analyst',
            ])
            ->assertRedirect();

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $license->id)
            ->where('requestable_type', License::class)
            ->firstOrFail();

        $this->assertSame(2, $checkoutRequest->quantity);
        $this->assertSame(CheckoutRequest::STATUS_PENDING, $checkoutRequest->status);
        $this->assertSame('user', $checkoutRequest->requested_for_type);
        $this->assertSame('Jane Analyst', $checkoutRequest->requested_for_display);
        $this->assertSame('2026-06-20', optional($checkoutRequest->needed_by_date)->format('Y-m-d'));

        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class);
    }

    public function test_license_request_requires_permission(): void
    {
        $requester = User::factory()->create();
        $discipline = Discipline::create(['name' => 'Validation', 'created_by' => 1]);
        $company = Company::factory()->create();
        $project = Project::factory()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'reassignable' => true,
            'seats' => 2,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'license', 'itemId' => $license->id]), [
                'request-quantity' => 1,
                'requested_discipline_id' => $discipline->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-20',
                'requested_for_type' => 'asset',
                'requested_for_display' => 'LT-2401',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
        ]);
    }

    public function test_requested_requests_api_returns_license_request_metadata(): void
    {
        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $company = Company::factory()->create(['name' => 'Rabat Office']);
        $discipline = Discipline::create(['name' => 'BIM', 'created_by' => $requester->id]);
        $project = Project::factory()->create(['name' => 'License Tracking Project']);
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'name' => 'Autodesk Seat Pool',
            'purchase_cost' => 700,
            'reassignable' => true,
            'seats' => 4,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forLicense()->create([
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
            'quantity' => 2,
            'project_id' => $project->id,
            'company_id' => $company->id,
            'requested_discipline_id' => $discipline->id,
            'requested_for_type' => 'asset',
            'requested_for_display' => 'WS-44',
            'needed_by_date' => '2026-07-15',
            'reusable_quantity' => 1,
            'due_back_before_needed_by_quantity' => 1,
            'procurement_shortfall' => 0,
            'reference_price_snapshot' => 700,
        ]);

        $license->licenseSeats()->orderBy('id')->firstOrFail()->forceFill([
            'assigned_to' => User::factory()->create()->id,
            'expected_release_date' => '2026-07-01',
        ])->save();

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['license_id' => $license->id]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $checkoutRequest->id)
            ->assertJsonPath('rows.0.name', 'Autodesk Seat Pool')
            ->assertJsonPath('rows.0.project', 'License Tracking Project')
            ->assertJsonPath('rows.0.company', 'Rabat Office')
            ->assertJsonPath('rows.0.requested_discipline', 'BIM')
            ->assertJsonPath('rows.0.requested_for_type', 'asset')
            ->assertJsonPath('rows.0.requested_for_display', 'WS-44')
            ->assertJsonPath('rows.0.expected_release_before_needed_by_quantity', 1);
    }

    public function test_license_request_cart_submit_creates_project_scoped_requests(): void
    {
        Notification::fake();

        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'License Cart', 'created_by' => $requester->id]);
        $project = Project::factory()->create();
        $coordinator = User::factory()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'reassignable' => true,
            'purchase_cost' => 200,
            'seats' => 2,
        ])->fresh();

        $license->licenseSeats()->orderBy('id')->skip(1)->firstOrFail()->forceFill([
            'assigned_to' => User::factory()->create()->id,
            'expected_release_date' => '2026-06-10',
        ])->save();

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->postJson(route('account.request-cart.licenses.items.add'), [
                'lines' => [[
                    'license_id' => $license->id,
                    'quantity' => 2,
                    'discipline_id' => $discipline->id,
                    'company_id' => $company->id,
                    'requested_for_type' => 'user',
                    'requested_for_display' => 'Amina Planner',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('cart_count', 1);

        $this->actingAs($requester)
            ->post(route('account.request-cart.licenses.submit'), [
                'project_id' => $project->id,
                'needed_by_date' => '2026-06-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
            'project_id' => $project->id,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'requested_for_type' => 'user',
            'requested_for_display' => 'Amina Planner',
            'quantity' => 2,
            'reusable_quantity' => 1,
            'due_back_before_needed_by_quantity' => 1,
            'potentially_coverable_quantity' => 2,
            'procurement_shortfall' => 0,
        ]);

        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class);
    }

    public function test_requested_requests_api_can_be_filtered_to_project_license_requests(): void
    {
        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create(['name' => 'Project Filter', 'created_by' => $requester->id]);
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'reassignable' => true,
            'seats' => 1,
        ]);
        $modelRequest = CheckoutRequest::factory()->create([
            'user_id' => $requester->id,
            'project_id' => $project->id,
        ]);
        $projectLicenseRequest = CheckoutRequest::factory()->forLicense()->create([
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
            'project_id' => $project->id,
            'company_id' => $company->id,
            'requested_discipline_id' => $discipline->id,
        ]);
        CheckoutRequest::factory()->forLicense()->create([
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
            'project_id' => $otherProject->id,
            'company_id' => $company->id,
            'requested_discipline_id' => $discipline->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.requests.index', ['project_id' => $project->id, 'requestable_type' => 'license']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $projectLicenseRequest->id)
            ->assertJsonMissing(['request_id' => $modelRequest->id]);
    }

    public function test_license_seats_api_returns_expected_release_date(): void
    {
        $viewer = User::factory()->superuser()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'reassignable' => true,
            'seats' => 1,
        ])->fresh();

        $seat = $license->licenseSeats()->firstOrFail();
        $seat->forceFill([
            'assigned_to' => User::factory()->create()->id,
            'expected_release_date' => '2026-07-01',
        ])->save();

        $this->actingAsForApi($viewer)
            ->getJson(route('api.licenses.seats.index', ['license' => $license->id, 'status' => 'assigned']))
            ->assertOk()
            ->assertJsonPath('rows.0.expected_release_date_value', '2026-07-01');
    }
}
