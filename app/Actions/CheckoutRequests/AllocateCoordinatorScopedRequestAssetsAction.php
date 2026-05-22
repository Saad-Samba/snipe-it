<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AllocateCoordinatorScopedRequestAssetsAction
{
    public static function run(CheckoutRequest $checkoutRequest, User $coordinator): array
    {
        if (! $checkoutRequest->canBeViewedBy($coordinator)) {
            throw new AuthorizationException('You are not authorized to allocate assets for this request.');
        }

        if ($checkoutRequest->requestable_type !== AssetModel::class) {
            throw new RuntimeException('Only model requests can be bulk allocated.');
        }

        $reservedStatusId = Setting::rfqReservedStatusId();
        if (! $reservedStatusId) {
            throw new RuntimeException('The RFQ reserved status must be configured before allocating assets.');
        }

        $remainingQuantity = $checkoutRequest->remainingAllocationQuantity();

        if ($remainingQuantity < 1) {
            return self::buildResult($checkoutRequest, 0);
        }

        $scopePairs = $checkoutRequest->coordinatorTargets()
            ->where('user_id', $coordinator->id)
            ->get(['company_id', 'discipline_id'])
            ->filter(fn ($scope) => $scope->company_id && $scope->discipline_id)
            ->values();

        if ($scopePairs->isEmpty()) {
            throw new AuthorizationException('You are not assigned to any eligible request scopes.');
        }

        $eligibleAssets = Asset::query()
            ->withoutGlobalScopes()
            ->RTD()
            ->where('model_id', $checkoutRequest->requestable_id)
            ->whereNull('project_id')
            ->where('status_id', '!=', $reservedStatusId)
            ->whereNotIn('id', function ($query) {
                $query->select('asset_id')->from('checkout_request_assets');
            })
            ->where(function ($query) use ($scopePairs) {
                foreach ($scopePairs as $scope) {
                    $query->orWhere(function ($scopeQuery) use ($scope) {
                        $scopeQuery
                            ->where('company_id', $scope->company_id)
                            ->where('discipline_id', $scope->discipline_id);
                    });
                }
            })
            ->orderBy('id')
            ->limit($remainingQuantity)
            ->get();

        $allocatedCount = 0;

        DB::transaction(function () use ($eligibleAssets, $checkoutRequest, $coordinator, $reservedStatusId, &$allocatedCount) {
            foreach ($eligibleAssets as $asset) {
                $asset->project_id = $checkoutRequest->project_id;
                $asset->status_id = $reservedStatusId;
                $asset->save();

                $checkoutRequest->allocatedAssets()->attach($asset->id, [
                    'allocated_by' => $coordinator->id,
                    'allocated_at' => now(),
                ]);

                $allocatedCount++;
            }
        });

        $checkoutRequest->refresh();
        $checkoutRequest->syncAllocationStatus(true);

        return self::buildResult($checkoutRequest->fresh(), $allocatedCount);
    }

    private static function buildResult(CheckoutRequest $checkoutRequest, int $allocatedCount): array
    {
        return [
            'request_id' => (int) $checkoutRequest->id,
            'allocated_count' => $allocatedCount,
            'allocated_total' => $checkoutRequest->allocatedQuantity(),
            'remaining_quantity' => $checkoutRequest->remainingAllocationQuantity(),
            'status' => $checkoutRequest->derivedAllocationStatus(),
        ];
    }
}
