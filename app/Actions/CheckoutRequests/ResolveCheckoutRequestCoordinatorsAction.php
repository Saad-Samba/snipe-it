<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\RegionalAssetCoordinatorAssignment;
use Illuminate\Support\Collection;

class ResolveCheckoutRequestCoordinatorsAction
{
    public static function run(CheckoutRequest $checkoutRequest, mixed $context = null): Collection
    {
        $sendAlternativeFollowUp = ! is_bool($context) || $context;

        if ($checkoutRequest->requestable_type !== AssetModel::class) {
            $checkoutRequest->coordinatorTargets()->delete();

            return collect();
        }

        $eligibleAssetPairs = Asset::query()
            ->RTD()
            ->where('model_id', $checkoutRequest->requestable_id)
            ->whereNotNull('company_id')
            ->whereNotNull('discipline_id')
            ->get(['company_id', 'discipline_id']);

        $checkoutRequest->coordinatorTargets()->delete();

        if ($eligibleAssetPairs->isEmpty()) {
            if ($sendAlternativeFollowUp) {
                SendAlternativeFollowUpNotificationAction::run($checkoutRequest);
            }

            return collect();
        }

        $reusableCountsByScope = $eligibleAssetPairs
            ->groupBy(fn (Asset $asset) => self::makeScopeKey((int) $asset->company_id, (int) $asset->discipline_id))
            ->map(fn (Collection $assets) => $assets->count());

        $assignments = RegionalAssetCoordinatorAssignment::query()
            ->with(['coordinator', 'company', 'discipline'])
            ->get();

        $matchedAssignments = $assignments->filter(function (RegionalAssetCoordinatorAssignment $assignment) use ($reusableCountsByScope) {
            return $reusableCountsByScope->has(self::makeScopeKey((int) $assignment->company_id, (int) $assignment->discipline_id));
        });

        foreach ($matchedAssignments as $assignment) {
            $checkoutRequest->coordinatorTargets()->create([
                'user_id' => $assignment->user_id,
                'company_id' => $assignment->company_id,
                'discipline_id' => $assignment->discipline_id,
            ]);
        }

        $resolvedCoordinators = $matchedAssignments
            ->filter(fn (RegionalAssetCoordinatorAssignment $assignment) => $assignment->coordinator)
            ->groupBy('user_id')
            ->map(function (Collection $userAssignments, int|string $userId) use ($reusableCountsByScope) {
                /** @var RegionalAssetCoordinatorAssignment $firstAssignment */
                $firstAssignment = $userAssignments->first();

                return [
                    'user_id' => (int) $userId,
                    'coordinator' => $firstAssignment->coordinator,
                    'reusable_quantity' => $userAssignments->sum(
                        fn (RegionalAssetCoordinatorAssignment $assignment) => (int) ($reusableCountsByScope[self::makeScopeKey((int) $assignment->company_id, (int) $assignment->discipline_id)] ?? 0)
                    ),
                ];
            })
            ->values();

        if ($sendAlternativeFollowUp) {
            SendAlternativeFollowUpNotificationAction::run($checkoutRequest);
        }

        return $resolvedCoordinators;
    }

    private static function makeScopeKey(int $companyId, int $disciplineId): string
    {
        return $companyId.'-'.$disciplineId;
    }
}
