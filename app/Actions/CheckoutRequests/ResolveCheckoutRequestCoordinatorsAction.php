<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\License;
use App\Models\RegionalAssetCoordinatorAssignment;
use Illuminate\Support\Collection;

class ResolveCheckoutRequestCoordinatorsAction
{
    public static function run(CheckoutRequest $checkoutRequest): Collection
    {
        if (! in_array($checkoutRequest->requestable_type, [AssetModel::class, License::class], true)) {
            $checkoutRequest->coordinatorTargets()->delete();

            return collect();
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
            $reusableCountsByScope = $eligibleAssetPairs
                ->groupBy(fn (array $scope) => self::makeScopeKey((int) $scope['company_id'], (int) $scope['discipline_id']))
                ->map(fn (Collection $scopes) => $scopes->count());
        } else {
            $license = License::query()
                ->whereKey($checkoutRequest->requestable_id)
                ->whereNotNull('company_id')
                ->whereNotNull('discipline_id')
                ->where('reassignable', true)
                ->first();

            $eligibleAssetPairs = collect();
            if ($license && $checkoutRequest->company_id && $checkoutRequest->requested_discipline_id) {
                $eligibleAssetPairs = collect([[
                    'company_id' => (int) $checkoutRequest->company_id,
                    'discipline_id' => (int) $checkoutRequest->requested_discipline_id,
                ]]);
            }

            $coverableQuantity = max(
                (int) ($checkoutRequest->reusable_quantity ?? 0) + (int) ($checkoutRequest->due_back_before_needed_by_quantity ?? 0),
                1
            );
            $reusableCountsByScope = $eligibleAssetPairs
                ->groupBy(fn (array $scope) => self::makeScopeKey((int) $scope['company_id'], (int) $scope['discipline_id']))
                ->map(fn () => $coverableQuantity);
        }

        $checkoutRequest->coordinatorTargets()->delete();

        if ($eligibleAssetPairs->isEmpty()) {
            return collect();
        }

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

        return $matchedAssignments
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
    }

    private static function makeScopeKey(int $companyId, int $disciplineId): string
    {
        return $companyId.'-'.$disciplineId;
    }
}
