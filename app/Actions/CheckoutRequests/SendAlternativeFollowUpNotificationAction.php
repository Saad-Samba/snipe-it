<?php

namespace App\Actions\CheckoutRequests;

use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\User;
use App\Notifications\RequestAlternativeFollowUpNotification;
use Illuminate\Support\Facades\DB;

class SendAlternativeFollowUpNotificationAction
{
    public static function run(CheckoutRequest $checkoutRequest): CheckoutRequest
    {
        $requestor = null;
        $afm = null;
        $shouldNotify = false;

        $checkoutRequest = DB::transaction(function () use ($checkoutRequest, &$requestor, &$afm, &$shouldNotify) {
            $lockedRequest = CheckoutRequest::query()
                ->lockForUpdate()
                ->findOrFail($checkoutRequest->id);

            if (
                $lockedRequest->alternative_follow_up_notified_at
                || ! $lockedRequest->requiresAlternativeFollowUp()
            ) {
                return $lockedRequest;
            }

            $requestor = $lockedRequest->requestingUser();
            if (! $requestor || $requestor->trashed() || ! $requestor->email) {
                return $lockedRequest;
            }

            $model = AssetModel::withoutGlobalScopes()
                ->with('category.manager')
                ->find($lockedRequest->requestable_id);
            $candidateAfm = $model?->category?->manager;

            if (
                $candidateAfm instanceof User
                && ! $candidateAfm->trashed()
                && $candidateAfm->email
                && strcasecmp($candidateAfm->email, $requestor->email) !== 0
            ) {
                $afm = $candidateAfm;
            }

            $lockedRequest->forceFill([
                'alternative_follow_up_notified_at' => now(),
            ])->save();
            $lockedRequest->syncAllocationStatus(true);
            $shouldNotify = true;

            return $lockedRequest->fresh();
        });

        if ($shouldNotify) {
            $requestor->notify(
                new RequestAlternativeFollowUpNotification($checkoutRequest, $afm)
            );
        }

        return $checkoutRequest;
    }
}
