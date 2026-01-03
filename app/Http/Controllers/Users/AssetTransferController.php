<?php

namespace App\Http\Controllers\Users;

use App\Events\CheckoutableCheckedIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferAssetsRequest;
use App\Http\Traits\MigratesLegacyAssetLocations;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\LicenseSeat;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AssetTransferController extends Controller
{
    use MigratesLegacyAssetLocations;

    public function store(TransferAssetsRequest $request, User $user): RedirectResponse
    {
        $actor = auth()->user();

        if (! ($actor->isSuperUser() || $actor->hasAccess('admin'))) {
            abort(403);
        }

        $targetUser = User::withTrashed()->findOrFail($request->input('transfer_target_user_id'));

        if ($targetUser->id === $user->id) {
            return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.error.transfer_same_user'));
        }

        $settings = Setting::getSettings();

        if ($settings->full_multiple_companies_support == 1 && ! $actor->isSuperUser()) {
            if (($user->company_id !== $actor->company_id) || ($targetUser->company_id !== $actor->company_id)) {
                return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.error.transfer_company_mismatch'));
            }
        }

        $assetIds = $request->boolean('transfer_all')
            ? Asset::where('assigned_type', User::class)->where('assigned_to', $user->id)->pluck('id')->all()
            : $request->input('ids', []);

        if (empty($assetIds)) {
            return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.error.transfer_no_assets'));
        }

        $assets = Asset::with(['licenseseats', 'assignedTo'])->whereIn('id', $assetIds)->get();

        if ($assets->isEmpty()) {
            return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.error.transfer_no_assets'));
        }

        $invalidAssets = $assets->filter(function (Asset $asset) use ($user) {
            return $asset->assigned_type !== User::class || $asset->assigned_to !== $user->id;
        });

        if ($invalidAssets->isNotEmpty()) {
            return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.error.transfer_invalid_assets'));
        }

        $failures = [];
        $transferred = 0;

        foreach ($assets as $asset) {
            // Respect FMCS for the asset/company combination
            if ($settings->full_multiple_companies_support == 1
                && $asset->company_id
                && $targetUser->company_id
                && $asset->company_id !== $targetUser->company_id) {
                $failures[] = $asset->asset_tag ?: $asset->id;
                continue;
            }

            DB::transaction(function () use ($asset, $actor, $targetUser, $settings, &$transferred, &$failures, $user) {
                $previousAssignee = $asset->assignedTo;
                $originalValues = $asset->getRawOriginal();

                $this->migrateLegacyLocations($asset);

                $asset->expected_checkin = null;
                $asset->last_checkin = now();
                $asset->assignedTo()->disassociate($asset);
                $asset->accepted = null;
                $asset->location_id = $asset->rtd_location_id;

                $asset->licenseseats->each(function (LicenseSeat $seat) {
                    $seat->update(['assigned_to' => null]);
                });

                CheckoutAcceptance::pending()
                    ->whereHasMorph(
                        'checkoutable',
                        [Asset::class],
                        function (Builder $query) use ($asset) {
                            $query->where('id', $asset->id);
                        }
                    )
                    ->get()
                    ->each
                    ->delete();

                if (! $asset->save()) {
                    $failures[] = $asset->asset_tag ?: $asset->id;
                    return;
                }

                event(new CheckoutableCheckedIn($asset, $previousAssignee, $actor, null, now(), $originalValues));

                // Re-check FMCS before checking out, mirroring AssetCheckoutController behaviour
                if (($settings->full_multiple_companies_support)
                    && (! is_null($targetUser->company_id))
                    && (! is_null($asset->company_id))
                    && ($targetUser->company_id != $asset->company_id)) {
                    $failures[] = $asset->asset_tag ?: $asset->id;
                    return;
                }

                if ($asset->checkOut($targetUser, $actor, now())) {
                    $transferred++;
                    return;
                }

                $failures[] = $asset->asset_tag ?: $asset->id;
            });
        }

        if ($transferred === 0) {
            return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.error.transfer_invalid_assets'));
        }

        $message = trans_choice('admin/users/message.success.transfer', $transferred, ['count' => $transferred]);

        if (! empty($failures)) {
            $message .= ' ' . trans('admin/hardware/message.undeployable', ['asset_tags' => implode(', ', $failures)]);
        }

        return redirect()->route('users.show', $user)->with('success', $message);
    }
}
