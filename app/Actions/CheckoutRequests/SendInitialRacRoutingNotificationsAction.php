<?php

namespace App\Actions\CheckoutRequests;

use App\Models\CheckoutRequestCoordinator;
use App\Models\CheckoutRequest;
use App\Notifications\RacScopedRequestSummaryNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SendInitialRacRoutingNotificationsAction
{
    public static function run(): int
    {
        $sent = 0;
        self::eligibleTargets()
            ->with([
                'checkoutRequest.requestedItem',
                'checkoutRequest.requestedDiscipline',
                'checkoutRequest.company',
                'checkoutRequest.project',
                'checkoutRequest.user',
                'coordinator',
                'discipline',
            ])
            ->chunkById(200, function (Collection $targets) use (&$sent) {
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
            });

        return $sent;
    }

    private static function eligibleTargets(): Builder
    {
        return CheckoutRequestCoordinator::query()
            ->whereNull('initial_notified_at')
            ->where(function (Builder $query) {
                $query->whereNull('resolution_status')
                    ->orWhereNotIn('resolution_status', CheckoutRequestCoordinator::terminalResolutionStatuses());
            })
            ->whereHas('coordinator', function (Builder $query) {
                $query->whereNotNull('email')
                    ->where('activated', true)
                    ->whereNull('deleted_at');
            })
            ->whereHas('checkoutRequest', function (Builder $query) {
                $query->whereIn('status', [
                    CheckoutRequest::STATUS_PENDING,
                    CheckoutRequest::STATUS_PARTIALLY_ALLOCATED,
                    CheckoutRequest::STATUS_IN_TRANSFER,
                ])
                    ->whereNull('canceled_at')
                    ->whereNull('fulfilled_at')
                    ->whereRaw('checkout_requests.quantity > (select count(*) from checkout_request_assets where checkout_request_assets.checkout_request_id = checkout_requests.id)');
            });
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
