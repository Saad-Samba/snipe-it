<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\CheckoutRequestAsset;
use Illuminate\Database\Eloquent\Model;

class CompleteCheckoutRequestTransferAction
{
    public static function run(Asset $asset, Model $checkoutTarget): void
    {
        $targetCompanyId = $checkoutTarget->company_id ?? null;

        if (! $targetCompanyId) {
            return;
        }

        $transfer = CheckoutRequestAsset::query()
            ->where('asset_id', $asset->id)
            ->where('transfer_destination_company_id', $targetCompanyId)
            ->whereNotNull('transfer_started_at')
            ->whereNull('transfer_completed_at')
            ->first();

        if (! $transfer) {
            return;
        }

        $transfer->forceFill(['transfer_completed_at' => now()])->save();

        CheckoutRequest::withoutGlobalScopes()
            ->find($transfer->checkout_request_id)
            ?->syncAllocationStatus(true);
    }
}
