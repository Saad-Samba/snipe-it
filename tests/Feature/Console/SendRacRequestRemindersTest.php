<?php

namespace Tests\Feature\Console;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\Statuslabel;
use App\Models\User;
use App\Notifications\RacScopedRequestSummaryNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendRacRequestRemindersTest extends TestCase
{
    public function test_sends_rac_request_reminder_after_48_hours_and_updates_tracking()
    {
        Notification::fake();
        [$coordinator, $request] = $this->makeCoordinatorTargetedRequest();

        $request->coordinatorTargets()->update([
            'initial_notified_at' => now()->subHours(49),
        ]);

        $this->artisan('snipeit:rac-request-reminders')
            ->expectsOutput('1 coordinators reminded.')
            ->assertExitCode(0);

        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class, function ($notification) use ($request) {
            return $notification->isReminder()
                && count($notification->lines()) === 1
                && $notification->lines()[0]['request_id'] === $request->id;
        });

        $this->assertDatabaseMissing('checkout_request_coordinators', [
            'checkout_request_id' => $request->id,
            'user_id' => $coordinator->id,
            'last_reminded_at' => null,
            'reminder_count' => 0,
        ]);
    }

    public function test_does_not_send_rac_request_reminder_before_48_hours()
    {
        Notification::fake();
        [$coordinator, $request] = $this->makeCoordinatorTargetedRequest();

        $request->coordinatorTargets()->update([
            'initial_notified_at' => now()->subHours(47),
        ]);

        $this->artisan('snipeit:rac-request-reminders')
            ->expectsOutput('0 coordinators reminded.')
            ->assertExitCode(0);

        Notification::assertNotSentTo($coordinator, RacScopedRequestSummaryNotification::class);
    }

    public function test_does_not_resend_rac_request_reminder_within_24_hours()
    {
        Notification::fake();
        [$coordinator, $request] = $this->makeCoordinatorTargetedRequest();

        $request->coordinatorTargets()->update([
            'initial_notified_at' => now()->subDays(3),
            'last_reminded_at' => now()->subHours(12),
            'reminder_count' => 1,
        ]);

        $this->artisan('snipeit:rac-request-reminders')
            ->expectsOutput('0 coordinators reminded.')
            ->assertExitCode(0);

        Notification::assertNotSentTo($coordinator, RacScopedRequestSummaryNotification::class);
    }

    public function test_batches_multiple_open_requests_for_same_coordinator_into_one_reminder()
    {
        Notification::fake();
        [$coordinator] = $this->makeCoordinatorTargetedRequest([
            'project_name' => 'Reminder Project A',
            'model_name' => 'Reminder Model A',
        ]);
        [, $requestB] = $this->makeCoordinatorTargetedRequest([
            'coordinator' => $coordinator,
            'project_name' => 'Reminder Project B',
            'model_name' => 'Reminder Model B',
        ]);

        CheckoutRequest::query()->each(function (CheckoutRequest $request) {
            $request->coordinatorTargets()->update([
                'initial_notified_at' => now()->subDays(3),
            ]);
        });

        $this->artisan('snipeit:rac-request-reminders')
            ->expectsOutput('1 coordinators reminded.')
            ->assertExitCode(0);

        $this->assertCount(1, Notification::sent($coordinator, RacScopedRequestSummaryNotification::class));
        Notification::assertSentTo($coordinator, RacScopedRequestSummaryNotification::class, function ($notification) use ($requestB) {
            $requestIds = collect($notification->lines())->pluck('request_id')->sort()->values()->all();

            return $notification->isReminder()
                && count($notification->lines()) === 2
                && in_array($requestB->id, $requestIds, true);
        });
    }

    public function test_skips_non_actionable_requests_and_reports_coordinators_without_email()
    {
        Notification::fake();
        [$processableCoordinator, $closedRequest] = $this->makeCoordinatorTargetedRequest([
            'coordinator_email' => '',
            'project_name' => 'Closed Reminder Project',
        ]);

        $closedRequest->update([
            'status' => CheckoutRequest::STATUS_FULFILLED,
            'fulfilled_at' => now(),
        ]);
        $closedRequest->coordinatorTargets()->update([
            'initial_notified_at' => now()->subDays(3),
        ]);

        [, $noEmailRequest] = $this->makeCoordinatorTargetedRequest([
            'coordinator' => $processableCoordinator,
            'coordinator_email' => '',
            'project_name' => 'No Email Reminder Project',
        ]);
        $noEmailRequest->coordinatorTargets()->update([
            'initial_notified_at' => now()->subDays(3),
        ]);

        $this->artisan('snipeit:rac-request-reminders')
            ->expectsOutput('0 coordinators reminded.')
            ->expectsOutput('The following coordinators do not have an email address:')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    private function makeCoordinatorTargetedRequest(array $overrides = []): array
    {
        $requester = User::factory()->create();
        $coordinator = $overrides['coordinator'] ?? User::factory()->create([
            'email' => $overrides['coordinator_email'] ?? 'rac@example.com',
        ]);
        if (array_key_exists('coordinator_email', $overrides) && (($overrides['coordinator'] ?? null) !== null)) {
            $coordinator->forceFill(['email' => $overrides['coordinator_email']])->save();
        }

        $companyAttributes = [];
        if (array_key_exists('company_name', $overrides)) {
            $companyAttributes['name'] = $overrides['company_name'];
        }
        $company = Company::factory()->create($companyAttributes);
        $disciplineAttributes = [
            'created_by' => $requester->id,
        ];
        if (array_key_exists('discipline_name', $overrides)) {
            $disciplineAttributes['name'] = $overrides['discipline_name'];
        } else {
            $disciplineAttributes['name'] = 'Reminder Discipline '.uniqid();
        }
        $discipline = Discipline::create($disciplineAttributes);
        $projectAttributes = [];
        if (array_key_exists('project_name', $overrides)) {
            $projectAttributes['name'] = $overrides['project_name'];
        }
        $project = Project::factory()->create($projectAttributes);
        $category = Category::factory()->forAssets()->create();
        $modelAttributes = [
            'category_id' => $category->id,
        ];
        if (array_key_exists('model_name', $overrides)) {
            $modelAttributes['name'] = $overrides['model_name'];
        }
        $model = AssetModel::factory()->create($modelAttributes);

        $this->createEligibleAsset($model, $company->id, $discipline->id);

        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $requester->id,
            'requestable_id' => $model->id,
            'requestable_type' => AssetModel::class,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quantity' => 1,
            'status' => CheckoutRequest::STATUS_PENDING,
        ]);

        $request->coordinatorTargets()->create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
        ]);

        return [$coordinator, $request];
    }

    private function createEligibleAsset(AssetModel $model, int $companyId, int $disciplineId): Asset
    {
        return Asset::factory()->create([
            'model_id' => $model->id,
            'company_id' => $companyId,
            'discipline_id' => $disciplineId,
            'status_id' => Statuslabel::factory()->rtd()->create()->id,
            'requestable' => 1,
            'assigned_to' => null,
            'assigned_type' => null,
        ]);
    }
}
