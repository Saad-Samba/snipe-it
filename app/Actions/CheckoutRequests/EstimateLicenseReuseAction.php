<?php

namespace App\Actions\CheckoutRequests;

use App\Models\License;

class EstimateLicenseReuseAction
{
    public static function run(License $license, int $requestedQuantity, ?string $neededByDate = null): array
    {
        $requestedQuantity = max($requestedQuantity, 1);
        $reusableNow = $license->availableReusableSeats()->count();
        $expectedReleaseQuantity = $license->expectedReleaseSeatsByDate($neededByDate)->count();
        $potentiallyCoverableQuantity = $reusableNow + $expectedReleaseQuantity;
        $coverableQuantity = min($requestedQuantity, $potentiallyCoverableQuantity);
        $procurementShortfall = max($requestedQuantity - $potentiallyCoverableQuantity, 0);
        $referencePrice = $license->purchase_cost !== null ? (float) $license->purchase_cost : 0.0;

        return [
            'requested_quantity' => $requestedQuantity,
            'reusable_now' => $reusableNow,
            'reusable_quantity' => $reusableNow,
            'expected_release_before_needed_by_quantity' => $expectedReleaseQuantity,
            'due_back_before_needed_by_quantity' => $expectedReleaseQuantity,
            'potentially_coverable_by_needed_by' => $potentiallyCoverableQuantity,
            'potentially_coverable_quantity' => $potentiallyCoverableQuantity,
            'procurement_shortfall' => $procurementShortfall,
            'estimated_savings' => round($coverableQuantity * $referencePrice, 2),
            'reference_price_snapshot' => $license->purchase_cost !== null ? $referencePrice : null,
        ];
    }
}
