<?php

namespace Tests\Feature\Requests;

use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Actions\CheckoutRequests\SendAlternativeFollowUpNotificationAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\CheckoutRequestCoordinator;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\User;
use App\Notifications\RequestAlternativeFollowUpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlternativeFollowUpNotificationTest extends TestCase
{
    public function test_request_without_a_matching_rac_immediately_notifies_requestor_and_copies_afm()
    {
        Notification::fake();
        [$request, $afm, $requestor] = $this->makeRequest();

        ResolveCheckoutRequestCoordinatorsAction::run($request);

        $request->refresh();

        $this->assertNotNull($request->alternative_follow_up_notified_at);
        $this->assertSame(CheckoutRequest::STATUS_NOT_ALLOCATED, $request->status);
        Notification::assertSentTo(
            $requestor,
            RequestAlternativeFollowUpNotification::class,
            function (RequestAlternativeFollowUpNotification $notification) use ($requestor, $afm) {
                $mail = $notification->toMail($requestor);

                return $mail->subject === 'Alternative model follow-up for request #'.$notification->checkoutRequest()->id
                    && $mail->cc === [[$afm->email, $afm->display_name]];
            }
        );

        SendAlternativeFollowUpNotificationAction::run($request);
        Notification::assertSentToTimes($requestor, RequestAlternativeFollowUpNotification::class, 1);
    }

    public function test_notification_waits_until_every_rac_target_is_terminal()
    {
        Notification::fake();
        [$request, , $requestor] = $this->makeRequest();
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

        SendAlternativeFollowUpNotificationAction::run($request);

        $this->assertNull($request->fresh()->alternative_follow_up_notified_at);
        Notification::assertNothingSent();

        $request->coordinatorTargets()
            ->where('user_id', $racB->id)
            ->firstOrFail()
            ->markCompletedNoStock();

        SendAlternativeFollowUpNotificationAction::run($request);

        $this->assertNotNull($request->fresh()->alternative_follow_up_notified_at);
        $this->assertSame(CheckoutRequest::STATUS_NOT_ALLOCATED, $request->fresh()->status);
        Notification::assertSentTo($requestor, RequestAlternativeFollowUpNotification::class);
    }

    public function test_fully_allocated_request_does_not_send_alternative_follow_up()
    {
        Notification::fake();
        [$request, , $requestor] = $this->makeRequest(['quantity' => 1]);
        $asset = Asset::factory()->create([
            'model_id' => $request->requestable_id,
        ]);
        $request->allocatedAssets()->attach($asset->id, [
            'allocated_by' => $request->user_id,
            'allocated_at' => now(),
        ]);

        SendAlternativeFollowUpNotificationAction::run($request);

        $this->assertNull($request->fresh()->alternative_follow_up_notified_at);
        Notification::assertNotSentTo($requestor, RequestAlternativeFollowUpNotification::class);
    }

    public function test_no_stock_action_closes_every_scope_and_sends_one_follow_up()
    {
        Notification::fake();
        [$request, , $requestor] = $this->makeRequest();
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
        Notification::assertSentToTimes(
            $requestor,
            RequestAlternativeFollowUpNotification::class,
            1
        );
    }

    public function test_notification_can_be_sent_without_an_afm_cc()
    {
        Notification::fake();
        [$request, , $requestor] = $this->makeRequest();
        $request->requestedItem->category->forceFill(['manager_id' => null])->save();

        SendAlternativeFollowUpNotificationAction::run($request);

        Notification::assertSentTo(
            $requestor,
            RequestAlternativeFollowUpNotification::class,
            fn (RequestAlternativeFollowUpNotification $notification) =>
                $notification->toMail($requestor)->cc === []
        );
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
            'name' => 'Alternative Follow-up '.Str::uuid(),
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
