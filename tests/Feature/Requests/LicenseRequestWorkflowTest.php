<?php

namespace Tests\Feature\Requests;

use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use App\Notifications\RequestAssetNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseRequestWorkflowTest extends TestCase
{
    public function test_license_request_persists_quantity_and_notifies_candidate_racs()
    {
        Notification::fake();

        $requester = User::factory()->requestLicenses()->create();
        $discipline = Discipline::create(['name' => 'Software', 'created_by' => $requester->id]);
        $coordinator = User::factory()->create(['first_name' => 'License', 'last_name' => 'RAC']);
        $company = Company::factory()->create(['name' => 'Casablanca Site']);
        $project = \App\Models\Project::factory()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'project_id' => null,
            'reassignable' => true,
            'expiration_date' => null,
            'termination_date' => null,
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
                'project_id' => $project->id,
            ])
            ->assertRedirect();

        $checkoutRequest = CheckoutRequest::query()
            ->where('user_id', $requester->id)
            ->where('requestable_id', $license->id)
            ->where('requestable_type', License::class)
            ->firstOrFail();

        $this->assertSame(2, $checkoutRequest->quantity);
        $this->assertSame(CheckoutRequest::STATUS_PENDING, $checkoutRequest->status);

        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        Notification::assertSentTo($coordinator, RequestAssetNotification::class);
    }

    public function test_license_request_requires_permission()
    {
        $requester = User::factory()->create();
        $discipline = Discipline::create(['name' => 'Validation', 'created_by' => 1]);
        $company = Company::factory()->create();
        $project = \App\Models\Project::factory()->create();
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
                'project_id' => $project->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('checkout_requests', [
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
        ]);
    }

    public function test_requested_assets_api_returns_license_request_metadata()
    {
        $requester = User::factory()->create();
        $project = \App\Models\Project::factory()->create(['name' => 'License Tracking Project']);
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'name' => 'Autodesk Seat Pool',
            'reassignable' => true,
            'seats' => 4,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forLicense()->create([
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
            'quantity' => 2,
            'project_id' => $project->id,
        ]);

        $this->actingAsForApi($requester)
            ->getJson(route('api.assets.requested', ['license_id' => $license->id]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.request_id', $checkoutRequest->id)
            ->assertJsonPath('rows.0.name', 'Autodesk Seat Pool')
            ->assertJsonPath('rows.0.project', 'License Tracking Project')
            ->assertJsonPath('rows.0.booked_count', 0);
    }

    public function test_license_checkout_links_seat_allocation_back_to_request()
    {
        $requester = User::factory()->create();
        $coordinator = User::factory()->checkoutLicenses()->create();
        $discipline = Discipline::create(['name' => 'Digital', 'created_by' => $requester->id]);
        $company = Company::factory()->create();
        $project = \App\Models\Project::factory()->create();
        $license = License::factory()->create([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'reassignable' => true,
            'seats' => 2,
        ]);

        $checkoutRequest = CheckoutRequest::factory()->forLicense()->create([
            'user_id' => $requester->id,
            'requestable_id' => $license->id,
            'requestable_type' => License::class,
            'quantity' => 1,
            'project_id' => $project->id,
        ]);

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $requester->id,
        ]);

        $checkoutRequest->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        $assignee = User::factory()->create();

        $this->actingAs($coordinator)
            ->post("/licenses/{$license->id}/checkout", [
                'assigned_to' => $assignee->id,
                'request_id' => $checkoutRequest->id,
                'notes' => 'Allocated from request',
            ])
            ->assertRedirect();

        $checkoutRequest->refresh();

        $this->assertDatabaseHas('checkout_request_license_seats', [
            'checkout_request_id' => $checkoutRequest->id,
            'allocated_by' => $coordinator->id,
        ]);
        $this->assertSame(CheckoutRequest::STATUS_FULLY_ALLOCATED, $checkoutRequest->status);
        $this->assertNotNull($checkoutRequest->fulfilled_at);
    }
}
