<?php

namespace App\Actions\CheckoutRequests;

use App\Models\AssetModel;
use Illuminate\Validation\ValidationException;

class EstimateAssetModelReuseAction
{
    public static function run(AssetModel $model, int $requestedQuantity): array
    {
        if ($model->reference_price === null) {
            throw ValidationException::withMessages([
                'reference_price' => 'Reference price is required before requesting this model.',
            ]);
        }

        $requestedQuantity = max($requestedQuantity, 1);
        $availableReusableStock = $model->availableAssets()->count();
        $reusableQuantity = min($requestedQuantity, $availableReusableStock);
        $procurementShortfall = max($requestedQuantity - $reusableQuantity, 0);
        $estimatedSavings = round($reusableQuantity * (float) $model->reference_price, 2);

        return [
            'requested_quantity' => $requestedQuantity,
            'available_reusable_stock' => $availableReusableStock,
            'reusable_quantity' => $reusableQuantity,
            'procurement_shortfall' => $procurementShortfall,
            'estimated_savings' => $estimatedSavings,
            'reference_price_snapshot' => (float) $model->reference_price,
        ];
    }
}
