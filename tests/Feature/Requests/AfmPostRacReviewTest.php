<?php

namespace Tests\Feature\Requests;

use App\Actions\CheckoutRequests\EvaluateAfmReviewRequirementAction;
use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\CheckoutRequestCoordinator;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\User;
use App\Notifications\AfmRequestReviewNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AfmPostRacReviewTest extends TestCase
{
    public function test_request_without_a_matching_rac_is_immediately_routed_to_afm_review()
    {
        Notification::fake();
        [$request, $afm] = $this->makeRequest();

        ResolveCheckoutRequestCoordinatorsAction::run($request);

        $request->refresh();

        $this->assertSame(CheckoutRequest::AFM_REVIEW_PENDING, $request->afm_review_status);
        $this->assertSame($afm->id, $request->afm_reviewer_id);
        $this->assertSame($request->quantity, $request->remainingAllocationQuantity());
        $this->assertNotNull($request->afm_review_requested_at);
        Notification::assertSentTo($afm, AfmRequestReviewNotification::class);

        EvaluateAfmReviewRequirementAction::run($request);
        Notification::assertSentToTimes($afm, AfmRequestReviewNotification::class, 1);
    }

    public function test_afm_review_waits_until_every_rac_target_is_terminal()
    {
        Notification::fake();
        [$request, $afm] = $this->makeRequest();
        $racA = User::factory()->create();
        $racB = User::factory()->create();

        $request->coordinatorTargets()->create([
            'user_id' => $racA->id,
            'company_id' => $request->company_id,
            'discipline_id' => $request->requested_discipline_id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK,
        ]);
        $request->coordinatorTargets()->create([
            'user_id' => $racB->id,
            'company_id' => $request->company_id,
            'discipline_id' => $request->requested_discipline_id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING,
        ]);

        EvaluateAfmReviewRequirementAction::run($request);

        $this->assertNull($request->fresh()->afm_review_status);
        Notification::assertNothingSent();

        $request->coordinatorTargets()
            ->where('user_id', $racB->id)
            ->firstOrFail()
            ->markCompletedNoStock();

        EvaluateAfmReviewRequirementAction::run($request);

        $this->assertSame(
            CheckoutRequest::AFM_REVIEW_PENDING,
            $request->fresh()->afm_review_status
        );
        Notification::assertSentTo($afm, AfmRequestReviewNotification::class);
    }

    public function test_fully_allocated_request_does_not_require_afm_review()
    {
        Notification::fake();
        [$request] = $this->makeRequest(['quantity' => 1]);
        $asset = Asset::factory()->create([
            'model_id' => $request->requestable_id,
        ]);
        $request->allocatedAssets()->attach($asset->id, [
            'allocated_by' => $request->user_id,
            'allocated_at' => now(),
        ]);

        EvaluateAfmReviewRequirementAction::run($request);

        $this->assertNull($request->fresh()->afm_review_status);
        Notification::assertNothingSent();
    }

    public function test_assigned_afm_can_confirm_the_post_rac_shortfall()
    {
        [$request, $afm] = $this->makeRequest(['quantity' => 3]);
        $request->forceFill([
            'afm_review_status' => CheckoutRequest::AFM_REVIEW_PENDING,
            'afm_reviewer_id' => $afm->id,
            'afm_review_requested_at' => now(),
        ])->save();

        $this->actingAs($afm)
            ->post(route('requests.afm.confirm', $request), [
                'afm_review_note' => 'All routed RACs confirmed that no additional reusable stock remains.',
            ])
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(CheckoutRequest::AFM_REVIEW_CONFIRMED, $request->afm_review_status);
        $this->assertSame($afm->id, $request->afm_reviewed_by);
        $this->assertSame(3, $request->afm_confirmed_shortfall);
        $this->assertNotNull($request->afm_reviewed_at);
        $this->assertSame(
            'All routed RACs confirmed that no additional reusable stock remains.',
            $request->afm_review_note
        );
    }

    public function test_unrelated_user_cannot_confirm_afm_review()
    {
        [$request, $afm] = $this->makeRequest();
        $request->forceFill([
            'afm_review_status' => CheckoutRequest::AFM_REVIEW_PENDING,
            'afm_reviewer_id' => $afm->id,
            'afm_review_requested_at' => now(),
        ])->save();

        $this->actingAs(User::factory()->create())
            ->post(route('requests.afm.confirm', $request))
            ->assertForbidden();

        $this->assertSame(
            CheckoutRequest::AFM_REVIEW_PENDING,
            $request->fresh()->afm_review_status
        );
    }

    public function test_no_stock_action_closes_every_scope_for_the_same_rac_and_opens_afm_review()
    {
        Notification::fake();
        [$request, $afm] = $this->makeRequest();
        $rac = User::factory()->create();
        $otherCompany = Company::factory()->create();

        foreach ([$request->company_id, $otherCompany->id] as $companyId) {
            $request->coordinatorTargets()->create([
                'user_id' => $rac->id,
                'company_id' => $companyId,
                'discipline_id' => $request->requested_discipline_id,
            ]);
        }

        $this->actingAs($rac)
            ->post(route('hardware.requests.coordinator-resolution', $request), [
                'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK,
            ])
            ->assertRedirect();

        $this->assertSame(
            2,
            $request->coordinatorTargets()
                ->where('resolution_status', CheckoutRequestCoordinator::RESOLUTION_COMPLETED_NO_STOCK)
                ->count()
        );
        $this->assertSame(
            CheckoutRequest::AFM_REVIEW_PENDING,
            $request->fresh()->afm_review_status
        );
        Notification::assertSentTo($afm, AfmRequestReviewNotification::class);
    }

    public function test_afm_review_queue_is_scoped_to_managed_asset_families()
    {
        [$ownRequest, $afm] = $this->makeRequest();
        [$otherRequest] = $this->makeRequest();

        foreach ([$ownRequest, $otherRequest] as $request) {
            $request->forceFill([
                'afm_review_status' => CheckoutRequest::AFM_REVIEW_PENDING,
                'afm_review_requested_at' => now(),
            ])->save();
        }

        $this->actingAs($afm)
            ->get(route('requests.afm.index'))
            ->assertOk()
            ->assertSee('#'.$ownRequest->id)
            ->assertDontSee('#'.$otherRequest->id);
    }

    private function makeRequest(array $attributes = []): array
    {
        $afm = User::factory()->create();
        $requestor = User::factory()->create();
        $category = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
        ]);
        $discipline = Discipline::create([
            'name' => 'AFM Review '.Str::uuid(),
            'created_by' => $requestor->id,
        ]);
        $company = Company::factory()->create();
        $project = Project::factory()->create();

        $request = CheckoutRequest::factory()->forAssetModel()->create(array_merge([
            'user_id' => $requestor->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 2,
            'status' => CheckoutRequest::STATUS_PENDING,
        ], $attributes));

        return [$request, $afm, $requestor];
    }
}
