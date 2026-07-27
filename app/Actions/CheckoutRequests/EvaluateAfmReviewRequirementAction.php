<?php

namespace App\Actions\CheckoutRequests;

use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Notifications\AfmRequestReviewNotification;
use Illuminate\Support\Facades\DB;

class EvaluateAfmReviewRequirementAction
{
    public static function run(CheckoutRequest $checkoutRequest): CheckoutRequest
    {
        $reviewer = null;
        $transitioned = false;

        $checkoutRequest = DB::transaction(function () use ($checkoutRequest, &$reviewer, &$transitioned) {
            $lockedRequest = CheckoutRequest::query()
                ->lockForUpdate()
                ->findOrFail($checkoutRequest->id);

            if (
                $lockedRequest->afm_review_status !== null
                || ! $lockedRequest->requiresAfmReview()
            ) {
                return $lockedRequest;
            }

            $model = AssetModel::withoutGlobalScopes()
                ->with('category.manager')
                ->find($lockedRequest->requestable_id);
            $reviewer = $model?->category?->manager;

            $lockedRequest->forceFill([
                'afm_review_status' => CheckoutRequest::AFM_REVIEW_PENDING,
                'afm_reviewer_id' => $reviewer?->id,
                'afm_review_requested_at' => now(),
            ])->save();
            $lockedRequest->syncAllocationStatus(true);
            $transitioned = true;

            return $lockedRequest->fresh();
        });

        if ($transitioned && $reviewer && ! $reviewer->trashed() && $reviewer->email) {
            $reviewer->notify(new AfmRequestReviewNotification($checkoutRequest));
        }

        return $checkoutRequest;
    }
}
