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
use App\Models\Statuslabel;
use App\Models\User;
use App\Notifications\RequestAlternativeFollowUpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlternativeFollowUpNotificationTest extends TestCase
{
    public function test_request_with_an_unrouted_reusable_scope_waits_for_rac_coverage()
    {
        Notification::fake();
        [$request, , $requestor] = $this->makeRequest();
        Asset::factory()->create([
            'model_id' => $request->requestable_id,
            'company_id' => $request->company_id,
            'discipline_id' => $request->requested_discipline_id,
            'status_id' => Statuslabel::factory()->rtd()->create()->id,
            'requestable' => 1,
            'assigned_to' => null,
            'assigned_type' => null,
        ]);

        ResolveCheckoutRequestCoordinatorsAction::run($request);

        $this->assertSame(
            CheckoutRequest::RAC_ROUTING_UNROUTED,
            $request->fresh()->rac_routing_status
        );
        $this->assertNull($request->fresh()->alternative_follow_up_notified_at);
        Notification::assertNotSentTo($requestor, RequestAlternativeFollowUpNotification::class);
    }

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

    public function test_failed_delivery_can_be_retried()
    {
        [$request, , $requestor] = $this->makeRequest();

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Mail transport unavailable'));

        try {
            SendAlternativeFollowUpNotificationAction::run($request);
            $this->fail('Expected notification delivery to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Mail transport unavailable', $exception->getMessage());
        }

        $this->assertNull($request->fresh()->alternative_follow_up_notified_at);

        Notification::fake();
        SendAlternativeFollowUpNotificationAction::run($request);

        $this->assertNotNull($request->fresh()->alternative_follow_up_notified_at);
        Notification::assertSentTo($requestor, RequestAlternativeFollowUpNotification::class);
    }

    public function test_models_from_the_same_submission_and_afm_are_sent_in_one_email()
    {
        Notification::fake();
        [$firstRequest, $afm, $requestor] = $this->makeRequest();
        $batchId = (string) Str::uuid();
        $firstRequest->forceFill(['submission_batch_id' => $batchId])->save();
        $secondRequest = $this->makeRelatedRequest($firstRequest, $afm, [
            'submission_batch_id' => $batchId,
        ]);
        $rac = User::factory()->create();

        $secondRequest->coordinatorTargets()->create([
            'user_id' => $rac->id,
            'company_id' => $secondRequest->company_id,
            'discipline_id' => $secondRequest->requested_discipline_id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING,
        ]);

        SendAlternativeFollowUpNotificationAction::run($firstRequest);

        Notification::assertNothingSent();
        $this->assertNull($firstRequest->fresh()->alternative_follow_up_notified_at);

        $secondRequest->coordinatorTargets()->firstOrFail()->markCompletedNoStock();
        SendAlternativeFollowUpNotificationAction::run($secondRequest);

        $this->assertNotNull($firstRequest->fresh()->alternative_follow_up_notified_at);
        $this->assertNotNull($secondRequest->fresh()->alternative_follow_up_notified_at);
        Notification::assertSentToTimes($requestor, RequestAlternativeFollowUpNotification::class, 1);
        Notification::assertSentTo(
            $requestor,
            RequestAlternativeFollowUpNotification::class,
            function (RequestAlternativeFollowUpNotification $notification) use ($requestor, $afm, $firstRequest, $secondRequest) {
                $notifiedRequestIds = $notification->checkoutRequests()->pluck('id')->sort()->values()->all();
                $mail = $notification->toMail($requestor);

                return $notifiedRequestIds === collect([$firstRequest->id, $secondRequest->id])->sort()->values()->all()
                    && $mail->subject === 'Alternative model follow-up for 2 requested models'
                    && $mail->cc === [[$afm->email, $afm->display_name]];
            }
        );
    }

    public function test_one_submission_sends_one_email_and_copies_every_concerned_afm()
    {
        Notification::fake();
        [$firstRequest, $firstAfm, $requestor] = $this->makeRequest();
        $batchId = (string) Str::uuid();
        $firstRequest->forceFill(['submission_batch_id' => $batchId])->save();
        $secondAfm = User::factory()->create();
        $secondRequest = $this->makeRelatedRequest($firstRequest, $secondAfm, [
            'submission_batch_id' => $batchId,
        ]);
        $rac = User::factory()->create();
        $secondRequest->coordinatorTargets()->create([
            'user_id' => $rac->id,
            'company_id' => $secondRequest->company_id,
            'discipline_id' => $secondRequest->requested_discipline_id,
            'resolution_status' => CheckoutRequestCoordinator::RESOLUTION_PENDING,
        ]);

        SendAlternativeFollowUpNotificationAction::run($firstRequest);

        Notification::assertNothingSent();
        $secondRequest->coordinatorTargets()->firstOrFail()->markCompletedNoStock();
        SendAlternativeFollowUpNotificationAction::run($secondRequest);

        Notification::assertSentToTimes($requestor, RequestAlternativeFollowUpNotification::class, 1);
        Notification::assertSentTo(
            $requestor,
            RequestAlternativeFollowUpNotification::class,
            function (RequestAlternativeFollowUpNotification $notification) use ($requestor, $firstAfm, $secondAfm, $firstRequest, $secondRequest) {
                $notifiedRequestIds = $notification->checkoutRequests()->pluck('id')->sort()->values()->all();
                $cc = collect($notification->toMail($requestor)->cc)
                    ->sortBy(fn (array $recipient) => $recipient[0])
                    ->values()
                    ->all();
                $expectedCc = collect([
                    [$firstAfm->email, $firstAfm->display_name],
                    [$secondAfm->email, $secondAfm->display_name],
                ])->sortBy(fn (array $recipient) => $recipient[0])->values()->all();

                return $notifiedRequestIds === collect([$firstRequest->id, $secondRequest->id])->sort()->values()->all()
                    && $cc === $expectedCc;
            }
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

    private function makeRelatedRequest(CheckoutRequest $request, User $afm, array $attributes = []): CheckoutRequest
    {
        $category = Category::factory()->forAssets()->create([
            'manager_id' => $afm->id,
        ]);
        $model = AssetModel::factory()->create([
            'category_id' => $category->id,
        ]);

        return CheckoutRequest::factory()->forAssetModel()->create(array_merge([
            'user_id' => $request->user_id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $request->requested_discipline_id,
            'company_id' => $request->company_id,
            'project_id' => $request->project_id,
            'quantity' => 2,
            'status' => CheckoutRequest::STATUS_PENDING,
        ], $attributes));
    }
}
