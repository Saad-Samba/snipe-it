<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\License;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Notifications\RequestAssetNotification;

class ResolveCheckoutRequestCoordinatorsAction
{
    public static function run(CheckoutRequest $checkoutRequest, array $notificationData = []): void
    {
        if (! in_array($checkoutRequest->requestable_type, [AssetModel::class, License::class], true)) {
            $checkoutRequest->coordinatorTargets()->delete();

            return;
        }

        if ($checkoutRequest->requestable_type === AssetModel::class) {
            $eligibleAssetPairs = Asset::query()
                ->RTD()
                ->where('model_id', $checkoutRequest->requestable_id)
                ->whereNotNull('company_id')
                ->whereNotNull('discipline_id')
                ->get(['company_id', 'discipline_id'])
                ->map(fn (Asset $asset) => [
                    'company_id' => (int) $asset->company_id,
                    'discipline_id' => (int) $asset->discipline_id,
                ])
                ->unique()
                ->values();
        } else {
            $eligibleAssetPairs = License::query()
                ->withCount('freeSeats as free_seats_count')
                ->whereKey($checkoutRequest->requestable_id)
                ->whereNotNull('company_id')
                ->whereNotNull('discipline_id')
                ->get()
                ->filter(fn (License $license) => $license->isReusableForRequest())
                ->map(fn (License $license) => [
                    'company_id' => (int) $license->company_id,
                    'discipline_id' => (int) $license->discipline_id,
                ])
                ->unique()
                ->values();
        }

        $checkoutRequest->coordinatorTargets()->delete();

        if ($eligibleAssetPairs->isEmpty()) {
            return;
        }

        $assignments = RegionalAssetCoordinatorAssignment::query()
            ->with('coordinator')
            ->get();

        $eligibleAssignmentKeys = $eligibleAssetPairs
            ->map(fn (array $pair) => $pair['company_id'].'-'.$pair['discipline_id'])
            ->all();

        $assignments = $assignments->filter(function (RegionalAssetCoordinatorAssignment $assignment) use ($eligibleAssignmentKeys) {
            return in_array($assignment->company_id.'-'.$assignment->discipline_id, $eligibleAssignmentKeys, true);
        });

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
