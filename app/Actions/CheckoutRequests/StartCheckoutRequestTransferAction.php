<?php

namespace App\Actions\CheckoutRequests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartCheckoutRequestTransferAction
{
    public static function run(int $checkoutRequestId, int $assetId, User $coordinator): CheckoutRequest
    {
        return DB::transaction(function () use ($checkoutRequestId, $assetId, $coordinator) {
            $checkoutRequest = CheckoutRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($checkoutRequestId);
            $asset = Asset::withoutGlobalScopes()->with('assetstatus')->lockForUpdate()->findOrFail($assetId);

            self::validateTransfer($checkoutRequest, $asset, $coordinator);

            $transferStatus = self::transferStatus();
            $destinationCompany = Company::withoutGlobalScopes()->find($checkoutRequest->company_id);
            $transferNote = sprintf(
                'Transfer for request #%d to %s started on %s.',
                $checkoutRequest->id,
                $destinationCompany?->name ?: 'company #'.$checkoutRequest->company_id,
                now()->toDateString()
            );

            $asset->status_id = $transferStatus->id;
            $asset->notes = trim(collect([$asset->notes, $transferNote])->filter()->implode("\n"));
            $asset->save();

            $checkoutRequest->allocatedAssets()->attach($asset->id, [
                'allocated_by' => $coordinator->id,
                'allocated_at' => now(),
                'transfer_source_company_id' => $asset->company_id,
                'transfer_destination_company_id' => $checkoutRequest->company_id,
                'transfer_started_at' => now(),
            ]);

            $checkoutRequest->syncAllocationStatus(true);

            $checkoutRequest->coordinatorTargets()
                ->where('user_id', $coordinator->id)
                ->where('company_id', $asset->company_id)
                ->where('discipline_id', $asset->discipline_id)
                ->get()
                ->each(function ($target) use ($checkoutRequest) {
                    $checkoutRequest->remainingAllocationQuantity() > 0
                        ? $target->markInProgress()
                        : $target->markCompleted();
                });

            SendAlternativeFollowUpNotificationAction::run($checkoutRequest);

            return $checkoutRequest->fresh();
        });
    }

    private static function validateTransfer(CheckoutRequest $checkoutRequest, Asset $asset, User $coordinator): void
    {
        if (! Setting::getSettings()?->full_multiple_companies_support) {
            throw ValidationException::withMessages([
                'asset' => 'Start transfer is only used when Full Multiple Companies Support is enabled.',
            ]);
        }

        $isAuthorizedSourceCoordinator = $coordinator->isSuperUser()
            || $checkoutRequest->coordinatorTargets()
                ->where('user_id', $coordinator->id)
                ->where('company_id', $asset->company_id)
                ->where('discipline_id', $asset->discipline_id)
                ->exists();

        abort_unless($isAuthorizedSourceCoordinator, 403);
        abort_unless($coordinator->can('update', $asset), 403);

        if (
            $checkoutRequest->requestable_type !== AssetModel::class
            || (int) $checkoutRequest->requestable_id !== (int) $asset->model_id
        ) {
            throw ValidationException::withMessages(['asset' => 'This asset does not match the requested model.']);
        }

        if (! $checkoutRequest->company_id || ! $asset->company_id || (int) $checkoutRequest->company_id === (int) $asset->company_id) {
            throw ValidationException::withMessages(['asset' => 'Start transfer is only available for an asset in a different company from the requester.']);
        }

        if ($checkoutRequest->remainingAllocationQuantity() < 1) {
            throw ValidationException::withMessages(['asset' => 'This request no longer has an unfilled quantity.']);
        }

        if (! $asset->availableForCheckout()) {
            throw ValidationException::withMessages(['asset' => 'The asset must be unassigned and reusable before a transfer can start.']);
        }

        if (DB::table('checkout_request_assets')->where('asset_id', $asset->id)->exists()) {
            throw ValidationException::withMessages(['asset' => 'This asset is already linked to a request.']);
        }
    }

    private static function transferStatus(): Statuslabel
    {
        $status = Statuslabel::query()
            ->whereIn('name', ['In Transfer', 'In-Transit'])
            ->orderByRaw("CASE WHEN name = 'In Transfer' THEN 0 ELSE 1 END")
            ->first();

        if (! $status || $status->deployable || $status->archived) {
            throw ValidationException::withMessages([
                'status_id' => 'Create a non-deployable, non-archived status named "In Transfer" before starting a transfer.',
            ]);
        }

        return $status;
    }
}
