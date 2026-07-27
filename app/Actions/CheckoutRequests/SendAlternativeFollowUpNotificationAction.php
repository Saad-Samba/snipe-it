<?php

namespace App\Actions\CheckoutRequests;

use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\User;
use App\Notifications\RequestAlternativeFollowUpNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendAlternativeFollowUpNotificationAction
{
    public static function run(CheckoutRequest $checkoutRequest): CheckoutRequest
    {
        $requestor = null;
        $afms = collect();
        $requestsToNotify = collect();
        $shouldNotify = false;

        $checkoutRequest = DB::transaction(function () use ($checkoutRequest, &$requestor, &$afms, &$requestsToNotify, &$shouldNotify) {
            $requestQuery = CheckoutRequest::query()
                ->where('user_id', $checkoutRequest->user_id)
                ->where('requestable_type', AssetModel::class);

            if ($checkoutRequest->submission_batch_id) {
                $requestQuery->where('submission_batch_id', $checkoutRequest->submission_batch_id);
            } else {
                $requestQuery->whereKey($checkoutRequest->id);
            }

            /** @var Collection<int, CheckoutRequest> $batchRequests */
            $batchRequests = $requestQuery
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $models = AssetModel::withoutGlobalScopes()
                ->with('category.manager')
                ->whereIn('id', $batchRequests->pluck('requestable_id'))
                ->get()
                ->keyBy('id');
            $batchRequests->each(
                fn (CheckoutRequest $request) => $request->setRelation(
                    'requestedItem',
                    $models->get($request->requestable_id)
                )
            );

            /** @var CheckoutRequest $lockedRequest */
            $lockedRequest = $batchRequests->firstWhere('id', $checkoutRequest->id);
            if (! $lockedRequest) {
                return CheckoutRequest::query()->findOrFail($checkoutRequest->id);
            }

            $hasUnfinishedRacHandling = $batchRequests->contains(
                fn (CheckoutRequest $request) => ! $request->canceled_at
                    && $request->remainingAllocationQuantity() > 0
                    && ! $request->racHandlingComplete()
            );
            if ($hasUnfinishedRacHandling) {
                return $lockedRequest;
            }

            $requestsToNotify = $batchRequests
                ->filter(fn (CheckoutRequest $request) => ! $request->alternative_follow_up_notified_at
                    && $request->requiresAlternativeFollowUp())
                ->values();
            if ($requestsToNotify->isEmpty()) {
                return $lockedRequest;
            }

            $requestor = $lockedRequest->requestingUser();
            if (! $requestor || $requestor->trashed() || ! $requestor->email) {
                return $lockedRequest;
            }

            $afms = $requestsToNotify
                ->map(fn (CheckoutRequest $request) => $request->requestedItem?->category?->manager)
                ->filter(fn ($candidateAfm) => $candidateAfm instanceof User
                    && ! $candidateAfm->trashed()
                    && $candidateAfm->email
                    && strcasecmp($candidateAfm->email, $requestor->email) !== 0)
                ->unique(fn (User $candidateAfm) => strtolower($candidateAfm->email))
                ->values();

            $notifiedAt = now();
            $requestsToNotify->each(function (CheckoutRequest $request) use ($notifiedAt) {
                $request->forceFill([
                    'alternative_follow_up_notified_at' => $notifiedAt,
                ])->save();
                $request->syncAllocationStatus(true);
            });
            $shouldNotify = true;

            return $lockedRequest->fresh();
        });

        if ($shouldNotify) {
            $requestor->notify(
                new RequestAlternativeFollowUpNotification($requestsToNotify, $afms)
            );
        }

        return $checkoutRequest;
    }
}
