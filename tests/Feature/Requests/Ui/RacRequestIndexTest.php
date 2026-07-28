<?php

namespace Tests\Feature\Requests\Ui;

use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\CheckoutRequestCoordinator;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\User;
use Tests\TestCase;

class RacRequestIndexTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->create();

        $this->get(route('account.rac-requests.index'))
            ->assertRedirect(route('login'));
    }

    public function test_rac_sees_only_their_open_requests_and_review_link(): void
    {
        $rac = User::factory()->create();
        $otherRac = User::factory()->create();
        $requestor = User::factory()->create();
        $company = Company::factory()->create();
        $discipline = Discipline::create([
            'name' => 'RAC Queue Discipline',
            'created_by' => $requestor->id,
        ]);

        $visibleModel = AssetModel::factory()->create(['name' => 'Visible RAC Queue Model']);
        $visibleRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'requestable_id' => $visibleModel->id,
            'user_id' => $requestor->id,
            'company_id' => $company->id,
            'requested_discipline_id' => $discipline->id,
            'quantity' => 4,
        ]);
        $visibleRequest->coordinatorTargets()->create([
            'user_id' => $rac->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING,
        ]);

        $hiddenModel = AssetModel::factory()->create(['name' => 'Other RAC Queue Model']);
        $hiddenRequest = CheckoutRequest::factory()->forAssetModel()->create([
            'requestable_id' => $hiddenModel->id,
            'user_id' => $requestor->id,
            'company_id' => $company->id,
            'requested_discipline_id' => $discipline->id,
        ]);
        $hiddenRequest->coordinatorTargets()->create([
            'user_id' => $otherRac->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING,
        ]);

        $this->actingAs($rac)
            ->get(route('account.rac-requests.index'))
            ->assertOk()
            ->assertSeeText(trans('general.rac_requests'))
            ->assertSeeText('Visible RAC Queue Model')
            ->assertDontSeeText('Other RAC Queue Model')
            ->assertSee(route('hardware.index', [
                'request_id' => $visibleRequest->id,
                'request_bucket' => 'reusable_now',
            ]));
    }

    public function test_completed_requests_are_separated_from_the_default_open_queue(): void
    {
        $rac = User::factory()->create();
        $request = CheckoutRequest::factory()->forAssetModel()->create();
        $request->coordinatorTargets()->create([
            'user_id' => $rac->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK,
        ]);

        $this->actingAs($rac)
            ->get(route('account.rac-requests.index'))
            ->assertOk()
            ->assertDontSeeText('#'.$request->id);

        $this->actingAs($rac)
            ->get(route('account.rac-requests.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSeeText('#'.$request->id)
            ->assertSeeText('No more stock');
    }

    public function test_rac_queue_navigation_is_shown_only_to_users_with_rac_work(): void
    {
        $rac = User::factory()->create();
        $regularUser = User::factory()->create();
        $request = CheckoutRequest::factory()->forAssetModel()->create();
        $request->coordinatorTargets()->create([
            'user_id' => $rac->id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING,
        ]);

        $this->actingAs($rac)
            ->get(route('view-assets'))
            ->assertOk()
            ->assertSee(route('account.rac-requests.index'), false);

        $this->actingAs($regularUser)
            ->get(route('view-assets'))
            ->assertOk()
            ->assertDontSee(route('account.rac-requests.index'), false);
    }
}
