<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Notifications\RequestAssetNotification;

class ResolveCheckoutRequestCoordinatorsAction
{
    public static function run(CheckoutRequest $checkoutRequest, array $notificationData = []): void
    {
        if ($checkoutRequest->requestable_type !== AssetModel::class || empty($checkoutRequest->requested_discipline_id)) {
            $checkoutRequest->coordinatorTargets()->delete();

            return;
        }

        $eligibleCompanyIds = Asset::query()
            ->RTD()
            ->where('model_id', $checkoutRequest->requestable_id)
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->unique()
            ->values();

        $checkoutRequest->coordinatorTargets()->delete();

        if ($eligibleCompanyIds->isEmpty()) {
            return;
        }

        $assignments = RegionalAssetCoordinatorAssignment::query()
            ->with('coordinator')
            ->where('discipline_id', $checkoutRequest->requested_discipline_id)
            ->whereIn('company_id', $eligibleCompanyIds)
            ->get();

        foreach ($assignments as $assignment) {
            $checkoutRequest->coordinatorTargets()->create([
                'user_id' => $assignment->user_id,
                'company_id' => $assignment->company_id,
                'discipline_id' => $assignment->discipline_id,
            ]);

            if (!empty($notificationData) && $assignment->coordinator) {
                $assignment->coordinator->notify(new RequestAssetNotification($notificationData));
            }
        }
    }
}
