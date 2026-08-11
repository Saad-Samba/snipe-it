<?php

namespace Tests\Feature\Requests;

use App\Actions\CheckoutRequests\EstimateLicenseReuseAction;
use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseReuseWorkflowTest extends TestCase
{
    public function test_new_request_page_uses_model_and_license_tabs(): void
    {
        $requester = User::factory()
            ->requestAssetModels()
            ->requestLicenses()
            ->viewLicenses()
            ->create();
        $this->createReusableLicense();

        $this->actingAs($requester)
            ->get(route('requestable-assets', ['type' => 'licenses']))
            ->assertOk()
            ->assertSeeText('New Request')
            ->assertSee('href="#modelRequests"', false)
            ->assertSee('href="#licenseRequests"', false)
            ->assertSee('class="tab-pane fade in active" id="licenseRequests"', false)
            ->assertDontSeeText('Reusable License Seats')
            ->assertDontSeeText('Reusable Asset Models')
            ->assertDontSeeText('Select the quantity and destination scope for the physical inventory you need.')
            ->assertDontSeeText('Choose a license pool, destination scope, and the user or asset that needs the seat.')
            ->assertDontSeeText('Request reusable asset models or license seats for a project and needed-by date.')
            ->assertSee('id="requestableLicensesBulkForm"', false)
            ->assertSeeText('Add Selected to Cart')
            ->assertSeeText('Go')
            ->assertSeeText('Reusable Seats')
            ->assertSeeText('Reference Price')
            ->assertSeeText('Total Needed')
            ->assertSeeText('Discipline')
            ->assertSeeText('Company')
            ->assertSeeText('Actions')
            ->assertSeeText('Add to Cart')
            ->assertSeeText('Request Cart')
            ->assertSeeText('Cart Totals')
            ->assertSeeText('Amount to Buy')
            ->assertDontSeeText('Source Scope')
            ->assertDontSee('<th data-sortable="false">Target</th>', false)
            ->assertDontSee('license-request-user-target', false)
            ->assertDontSee('license-request-asset-target', false);
    }

    public function test_estimate_counts_reusable_and_expected_release_seats_using_unit_cost(): void
    {
        $license = $this->createReusableLicense(['purchase_cost' => 900, 'seats' => 3]);
        $seats = $license->licenseSeats()->orderBy('id')->get();
        $seats[1]->forceFill([
            'assigned_to' => User::factory()->create()->id,
            'expected_release_date' => '2026-09-01',
        ])->save();
        $seats[2]->forceFill([
            'assigned_to' => User::factory()->create()->id,
            'expected_release_date' => '2026-10-01',
        ])->save();

        $estimate = EstimateLicenseReuseAction::run($license, 3, '2026-09-15');

        $this->assertSame(1, $estimate['reusable_quantity']);
        $this->assertSame(1, $estimate['due_back_before_needed_by_quantity']);
        $this->assertSame(1, $estimate['procurement_shortfall']);
        $this->assertSame(300.0, $estimate['reference_price_snapshot']);
        $this->assertSame(600.0, $estimate['estimated_savings']);
    }

    public function test_license_cart_defers_target_selection_and_routes_to_source_inventory_rac(): void
    {
        Notification::fake();
        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $sourceCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $sourceDiscipline = $this->createDiscipline('Source Software', $requester);
        $destinationDiscipline = $this->createDiscipline('Destination Engineering', $requester);
        $license = $this->createReusableLicense([
            'company_id' => $sourceCompany->id,
            'discipline_id' => $sourceDiscipline->id,
            'seats' => 2,
        ]);
        $target = User::factory()->create(['company_id' => $destinationCompany->id]);
        $sourceRac = User::factory()->checkoutLicenses()->create();
        $destinationRac = User::factory()->checkoutLicenses()->create();
        $project = Project::factory()->create();

        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $sourceRac->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $sourceDiscipline->id,
            'created_by' => $requester->id,
        ]);
        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $destinationRac->id,
            'company_id' => $destinationCompany->id,
            'discipline_id' => $destinationDiscipline->id,
            'created_by' => $requester->id,
        ]);

        $this->actingAs($requester)->postJson(route('account.request-cart.licenses.items.add'), [
            'lines' => [[
                'license_id' => $license->id,
                'quantity' => 1,
                'discipline_id' => $destinationDiscipline->id,
                'company_id' => $destinationCompany->id,
            ]],
        ])->assertOk()->assertJsonPath('cart_count', 1);

        $this->actingAs($requester)->post(route('account.request-cart.licenses.submit'), [
            'project_id' => $project->id,
            'needed_by_date' => '2026-09-15',
        ])->assertRedirect();

        $checkoutRequest = CheckoutRequest::query()
            ->where('requestable_type', License::class)
            ->where('requestable_id', $license->id)
            ->firstOrFail();

        $this->assertNull($checkoutRequest->requested_for_type);
        $this->assertNull($checkoutRequest->requested_for_id);
        $this->assertNull($checkoutRequest->requested_for_display);
        $this->assertDatabaseHas('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $sourceRac->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $sourceDiscipline->id,
        ]);
        $this->assertDatabaseMissing('checkout_request_coordinators', [
            'checkout_request_id' => $checkoutRequest->id,
            'user_id' => $destinationRac->id,
        ]);

        $this->actingAs($sourceRac)->post(route('licenses.checkout', $license), [
            'request_id' => $checkoutRequest->id,
            'assigned_to' => $target->id,
            'expected_release_date' => '2026-09-30',
        ])->assertRedirect(route('rac-requests.index'));

        $checkoutRequest->refresh();
        $this->assertSame(User::class, $checkoutRequest->requested_for_type);
        $this->assertSame($target->id, $checkoutRequest->requested_for_id);
        $this->assertSame($target->display_name, $checkoutRequest->requested_for_display);
    }

    public function test_fulfillment_rejects_wrong_target_and_over_allocation(): void
    {
        [$request, $license, $target, $coordinator] = $this->createRoutedLicenseRequest();

        $this->actingAs($coordinator)->post(route('licenses.checkout', $license), [
            'request_id' => $request->id,
            'assigned_to' => User::factory()->create()->id,
        ])->assertStatus(422);

        $this->actingAs($coordinator)->post(route('licenses.checkout', $license), [
            'request_id' => $request->id,
            'assigned_to' => $target->id,
            'expected_release_date' => '2026-09-30',
        ])->assertRedirect(route('rac-requests.index'));

        $request->refresh();
        $this->assertSame(CheckoutRequest::STATUS_FULLY_ALLOCATED, $request->status);
        $this->assertSame(1, $request->allocatedLicenseSeats()->count());

        $this->actingAs($coordinator)->post(route('licenses.checkout', $license), [
            'request_id' => $request->id,
            'assigned_to' => $target->id,
        ])->assertStatus(409);
    }

    public function test_checked_in_seat_can_fulfill_a_later_request_and_keeps_history(): void
    {
        [$firstRequest, $license, $firstTarget, $coordinator] = $this->createRoutedLicenseRequest();
        $seat = $license->licenseSeats()->firstOrFail();

        $this->actingAs($coordinator)->post(route('licenses.checkout', $license), [
            'request_id' => $firstRequest->id,
            'assigned_to' => $firstTarget->id,
            'expected_release_date' => '2026-09-30',
        ])->assertRedirect();

        $this->actingAs($coordinator)->post(route('licenses.checkin.save', $seat->id), [
            'notes' => 'Released for reuse',
        ])->assertRedirect();

        $seat->refresh();
        $this->assertNull($seat->assigned_to);
        $this->assertNull($seat->expected_release_date);

        $secondTarget = User::factory()->create(['company_id' => $firstRequest->company_id]);
        $secondRequest = $license->request(1, [
            'user_id' => $firstRequest->user_id,
            'company_id' => $firstRequest->company_id,
            'project_id' => Project::factory()->create()->id,
            'needed_by_date' => '2026-10-15',
            'requested_discipline_id' => $firstRequest->requested_discipline_id,
            'requested_for_type' => User::class,
            'requested_for_id' => $secondTarget->id,
            'requested_for_display' => $secondTarget->display_name,
        ]);
        ResolveCheckoutRequestCoordinatorsAction::run($secondRequest, false);

        $this->actingAs($coordinator)->post(route('licenses.checkout', [
            'license' => $license,
            'seatId' => $seat->id,
        ]), [
            'request_id' => $secondRequest->id,
            'assigned_to' => $secondTarget->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('checkout_request_license_seats', 2);
        $this->assertSame(2, $seat->checkoutRequests()->count());
    }

    public function test_api_checkin_clears_expected_release_date(): void
    {
        $admin = User::factory()->superuser()->create();
        $license = $this->createReusableLicense(['seats' => 1]);
        $seat = $license->licenseSeats()->firstOrFail();
        $target = User::factory()->create();
        $seat->forceFill([
            'assigned_to' => $target->id,
            'expected_release_date' => '2026-09-30',
        ])->save();

        $this->actingAsForApi($admin)->patchJson(
            route('api.licenses.seats.update', [$license->id, $seat->id]),
            ['assigned_to' => null, 'asset_id' => null]
        )->assertOk();

        $this->assertNull($seat->fresh()->expected_release_date);
    }

    private function createRoutedLicenseRequest(): array
    {
        $requester = User::factory()->requestLicenses()->viewLicenses()->create();
        $sourceCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $sourceDiscipline = $this->createDiscipline('Source', $requester);
        $destinationDiscipline = $this->createDiscipline('Destination', $requester);
        $license = $this->createReusableLicense([
            'company_id' => $sourceCompany->id,
            'discipline_id' => $sourceDiscipline->id,
            'seats' => 1,
        ]);
        $target = User::factory()->create(['company_id' => $destinationCompany->id]);
        $coordinator = User::factory()->checkoutLicenses()->create();
        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $sourceCompany->id,
            'discipline_id' => $sourceDiscipline->id,
            'created_by' => $requester->id,
        ]);

        $request = $license->request(1, [
            'user_id' => $requester->id,
            'company_id' => $destinationCompany->id,
            'project_id' => Project::factory()->create()->id,
            'needed_by_date' => '2026-09-15',
            'requested_discipline_id' => $destinationDiscipline->id,
            'requested_for_type' => User::class,
            'requested_for_id' => $target->id,
            'requested_for_display' => $target->display_name,
        ]);
        ResolveCheckoutRequestCoordinatorsAction::run($request, false);

        return [$request, $license, $target, $coordinator];
    }

    private function createReusableLicense(array $attributes = []): License
    {
        $attributes['company_id'] ??= Company::factory()->create()->id;
        $attributes['discipline_id'] ??= Discipline::create([
            'name' => 'License Discipline ' . uniqid(),
            'created_by' => User::factory()->create()->id,
        ])->id;

        return License::factory()->create(array_merge([
            'category_id' => Category::factory()->forLicenses()->create()->id,
            'reassignable' => true,
            'perpetual' => true,
            'expiration_date' => null,
            'termination_date' => null,
            'purchase_cost' => 100,
            'seats' => 1,
        ], $attributes))->fresh();
    }

    private function createDiscipline(string $name, User $creator): Discipline
    {
        return Discipline::create(['name' => $name, 'created_by' => $creator->id]);
    }
}
