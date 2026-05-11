<?php

namespace App\Actions\CheckoutRequests;

use App\Models\AssetModel;
class EstimateAssetModelReuseAction
{
    public static function run(AssetModel $model, int $requestedQuantity): array
    {
        $requestedQuantity = max($requestedQuantity, 1);
        $availableReusableStock = $model->availableAssets()->count();
        $reusableQuantity = min($requestedQuantity, $availableReusableStock);
        $procurementShortfall = max($requestedQuantity - $reusableQuantity, 0);
        $referencePrice = $model->reference_price !== null ? (float) $model->reference_price : 0.0;
        $estimatedSavings = round($reusableQuantity * $referencePrice, 2);

        return [
            'requested_quantity' => $requestedQuantity,
            'available_reusable_stock' => $availableReusableStock,
            'reusable_quantity' => $reusableQuantity,
            'procurement_shortfall' => $procurementShortfall,
            'estimated_savings' => $estimatedSavings,
            'reference_price_snapshot' => $model->reference_price !== null ? $referencePrice : null,
        ];
    }
}
