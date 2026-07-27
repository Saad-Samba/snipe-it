<?php

namespace App\Actions\CheckoutRequests;

use App\Models\CheckoutRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmAfmReviewAction
{
    public static function run(
        CheckoutRequest $checkoutRequest,
        User $actor,
        ?string $note
    ): CheckoutRequest {
        return DB::transaction(function () use ($checkoutRequest, $actor, $note) {
            $lockedRequest = CheckoutRequest::query()
                ->lockForUpdate()
                ->findOrFail($checkoutRequest->id);

            if (! $lockedRequest->isManagedByAfm($actor)) {
                throw new AuthorizationException('You are not authorized to review this request.');
            }

            if (! $lockedRequest->awaitsAfmReview()) {
                throw ValidationException::withMessages([
                    'afm_review_status' => 'This request is not awaiting AFM review.',
                ]);
            }

            $lockedRequest->forceFill([
                'afm_review_status' => CheckoutRequest::AFM_REVIEW_CONFIRMED,
                'afm_reviewed_by' => $actor->id,
                'afm_reviewed_at' => now(),
                'afm_confirmed_shortfall' => $lockedRequest->remainingAllocationQuantity(),
                'afm_review_note' => $note,
            ])->save();
            $lockedRequest->syncAllocationStatus(true);

            return $lockedRequest->fresh();
        });
    }
}
