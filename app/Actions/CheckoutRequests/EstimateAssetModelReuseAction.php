<?php

namespace App\Actions\CheckoutRequests;

use App\Models\AssetModel;
class EstimateAssetModelReuseAction
{
    public static function run(AssetModel $model, int $requestedQuantity, ?string $neededByDate = null): array
    {
        $requestedQuantity = max($requestedQuantity, 1);
        $availableReusableStock = $model->availableAssets()->count();
        $dueBackBeforeNeededBy = $neededByDate ? $model->dueBackAssetsByDate($neededByDate)->count() : 0;
        $potentiallyCoverableByNeededBy = $availableReusableStock + $dueBackBeforeNeededBy;
        $coverableQuantity = min($requestedQuantity, $potentiallyCoverableByNeededBy);
        $procurementShortfall = max($requestedQuantity - $potentiallyCoverableByNeededBy, 0);
        $referencePrice = $model->reference_price !== null ? (float) $model->reference_price : 0.0;
        $estimatedSavings = round($coverableQuantity * $referencePrice, 2);

        return [
            'requested_quantity' => $requestedQuantity,
            'reusable_now' => $availableReusableStock,
            'reusable_quantity' => $availableReusableStock,
            'due_back_before_needed_by_quantity' => $dueBackBeforeNeededBy,
            'potentially_coverable_by_needed_by' => $potentiallyCoverableByNeededBy,
            'potentially_coverable_quantity' => $potentiallyCoverableByNeededBy,
            'procurement_shortfall' => $procurementShortfall,
            'estimated_savings' => $estimatedSavings,
            'reference_price_snapshot' => $model->reference_price !== null ? $referencePrice : null,
        ];
    }
}
