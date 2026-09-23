<?php

namespace App\Console\Commands;

use App\Models\CheckoutRequestCoordinator;
use App\Notifications\RacScopedRequestSummaryNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendRacRequestReminders extends Command
{
    protected $signature = 'snipeit:rac-request-reminders';

    protected $description = 'Send reminder emails to regional asset coordinators for open reusable-scope requests awaiting action.';

    public function handle(): int
    {
        $eligibleTargets = CheckoutRequestCoordinator::query()
            ->with([
                'checkoutRequest.requestedItem',
                'checkoutRequest.requestedDiscipline',
                'checkoutRequest.company',
                'checkoutRequest.project',
                'checkoutRequest.user',
                'coordinator',
                'discipline',
            ])
            ->whereNotNull('initial_notified_at')
            ->get()
            ->filter(fn (CheckoutRequestCoordinator $target) => $this->shouldRemind($target))
            ->values();

        $groupedTargets = $eligibleTargets->groupBy('user_id');
        $noEmailList = [];
        $sentCount = 0;

        foreach ($groupedTargets as $targets) {
            /** @var CheckoutRequestCoordinator|null $firstTarget */
            $firstTarget = $targets->first();
            $coordinator = $firstTarget?->coordinator;
            $email = $coordinator?->email;

            if (! $coordinator || ! $email) {
                if ($coordinator) {
                    $noEmailList[] = [
                        'id' => $coordinator->id,
                        'name' => $coordinator->display_name,
                    ];
                }
                continue;
            }

            $summary = $this->buildReminderSummary($targets);
            if (empty($summary['lines'])) {
                continue;
            }

            $coordinator->notify(new RacScopedRequestSummaryNotification($summary, true));
            $sentCount++;

            CheckoutRequestCoordinator::query()
                ->whereIn('id', $targets->pluck('id')->all())
                ->update([
                    'last_reminded_at' => now(),
                    'reminder_count' => DB::raw('reminder_count + 1'),
                ]);
        }

        $this->info($sentCount.' coordinators reminded.');

        if (! empty($noEmailList)) {
            $this->info('The following coordinators do not have an email address:');
            $this->table(['ID', 'Name'], array_map(fn ($user) => [$user['id'], $user['name']], $noEmailList));
        }

        return 0;
    }

    private function shouldRemind(CheckoutRequestCoordinator $target): bool
    {
        if (! $target->coordinator || ! $target->checkoutRequest) {
            return false;
        }

        if ($target->initial_notified_at === null || $target->initial_notified_at->gt(now()->subHours(48))) {
            return false;
        }

        if ($target->last_reminded_at !== null && $target->last_reminded_at->gt(now()->subDay())) {
            return false;
        }

        if ($target->hasTerminalResolution()) {
            return false;
        }

        if (! $target->checkoutRequest->canBeProcessedBy($target->coordinator)) {
            return false;
        }

        return true;
    }

    private function buildReminderSummary(Collection $targets): array
    {
        /** @var CheckoutRequestCoordinator $firstTarget */
        $firstTarget = $targets->first();
        $uniqueRequesterIds = $targets->pluck('checkoutRequest.user_id')->filter()->unique();
        $singleRequester = $uniqueRequesterIds->count() === 1
            ? optional($targets->firstWhere('checkoutRequest.user_id', $uniqueRequesterIds->first()))->checkoutRequest?->requestingUser()
            : null;
        $earliestSubmittedAt = $targets
            ->pluck('checkoutRequest.created_at')
            ->filter()
            ->sort()
            ->first();

        return [
            'rac_user' => $firstTarget->coordinator,
            'requester' => $singleRequester,
            'submitted_at' => optional($earliestSubmittedAt)->toDateTimeString() ?? now()->toDateTimeString(),
            'project_name' => $targets->pluck('checkoutRequest.project.name')->filter()->unique()->count() === 1
                ? $targets->pluck('checkoutRequest.project.name')->filter()->first()
                : null,
            'lines' => $targets
                ->groupBy('checkout_request_id')
                ->map(function (Collection $requestTargets) {
                    /** @var CheckoutRequestCoordinator $target */
                    $target = $requestTargets->first();
                    $request = $target->checkoutRequest;
                    $liveMetrics = $request?->liveRequestMetrics() ?? [];

                    return [
                        'request_id' => (int) $request->id,
                        'model_name' => $request->requestedItem()?->name ?? $request->name(),
                        'project_name' => optional($request->project)->name ?: '-',
                        'company_name' => optional($request->company)->name ?: '-',
                        'discipline_name' => optional($request->requestedDiscipline)->name ?: '-',
                        'inventory_discipline_names' => $requestTargets
                            ->pluck('discipline.name')
                            ->filter()
                            ->unique()
                            ->sort()
                            ->values()
                            ->all(),
                        'requested_quantity' => (int) $request->quantity,
                        'reusable_quantity' => (int) ($liveMetrics['reusable_quantity'] ?? $request->reusable_quantity ?? 0),
                        'needed_by_date' => optional($request->needed_by_date)?->format('Y-m-d') ?: '-',
                        'model_show_url' => route('models.show', $request->requestable_id),
                        'project_requests_url' => $request->project_id
                            ? route('projects.show', ['project' => $request->project_id, 'tab' => 'requests'])
                            : route('requests.index'),
                        'request_detail_url' => route('hardware.index', [
                            'request_id' => $request->id,
                            'request_bucket' => 'reusable_now',
                        ]),
                    ];
                })->values()->all(),
        ];
    }
}
