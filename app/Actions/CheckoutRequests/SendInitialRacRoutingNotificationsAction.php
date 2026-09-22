<?php

namespace App\Actions\CheckoutRequests;

use App\Models\CheckoutRequestCoordinator;
use App\Notifications\RacScopedRequestSummaryNotification;
use Illuminate\Support\Collection;

class SendInitialRacRoutingNotificationsAction
{
    public static function run(): int
    {
        $targets = CheckoutRequestCoordinator::query()
            ->with([
                'checkoutRequest.requestedItem',
                'checkoutRequest.requestedDiscipline',
                'checkoutRequest.company',
                'checkoutRequest.project',
                'checkoutRequest.user',
                'coordinator',
                'discipline',
            ])
            ->whereNull('initial_notified_at')
            ->get()
            ->filter(fn (CheckoutRequestCoordinator $target) =>
                $target->coordinator?->email
                && $target->checkoutRequest
                && ! $target->hasTerminalResolution()
                && $target->checkoutRequest->canBeProcessedBy($target->coordinator)
            )
            ->values();

        $sent = 0;
        foreach ($targets->groupBy('user_id') as $coordinatorTargets) {
            /** @var CheckoutRequestCoordinator $first */
            $first = $coordinatorTargets->first();
            $first->coordinator->notify(new RacScopedRequestSummaryNotification(
                self::summary($coordinatorTargets)
            ));

            CheckoutRequestCoordinator::query()
                ->whereIn('id', $coordinatorTargets->pluck('id')->all())
                ->whereNull('initial_notified_at')
                ->update(['initial_notified_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    private static function summary(Collection $targets): array
    {
        /** @var CheckoutRequestCoordinator $first */
        $first = $targets->first();
        $requesterIds = $targets->pluck('checkoutRequest.user_id')->filter()->unique();
        $requester = $requesterIds->count() === 1
            ? $targets->firstWhere('checkoutRequest.user_id', $requesterIds->first())?->checkoutRequest?->requestingUser()
            : null;

        return [
            'rac_user' => $first->coordinator,
            'requester' => $requester,
            'submitted_at' => optional($targets->pluck('checkoutRequest.created_at')->filter()->sort()->first())->toDateTimeString() ?? now()->toDateTimeString(),
            'project_name' => $targets->pluck('checkoutRequest.project.name')->filter()->unique()->count() === 1
                ? $targets->pluck('checkoutRequest.project.name')->filter()->first()
                : null,
            'lines' => $targets->groupBy('checkout_request_id')->map(function (Collection $requestTargets) {
                /** @var CheckoutRequestCoordinator $target */
                $target = $requestTargets->first();
                $request = $target->checkoutRequest;
                $metrics = $request->liveRequestMetrics();

                return [
                    'request_id' => (int) $request->id,
                    'model_name' => $request->requestedItem()?->name ?? $request->name(),
                    'project_name' => optional($request->project)->name ?: '-',
                    'company_name' => optional($request->company)->name ?: '-',
                    'discipline_name' => optional($request->requestedDiscipline)->name ?: '-',
                    'inventory_discipline_names' => $requestTargets->pluck('discipline.name')->filter()->unique()->sort()->values()->all(),
                    'requested_quantity' => (int) $request->quantity,
                    'reusable_quantity' => (int) ($metrics['reusable_quantity'] ?? 0),
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
