<?php

namespace App\Services;

use App\Events\CheckoutableCheckedIn;
use App\Http\Traits\MigratesLegacyAssetLocations;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetCheckinService
{
    use MigratesLegacyAssetLocations;

    /**
     * @return array{checked_in:int,already_checked_in:array,failures:array}
     */
    public function checkinAssets(Collection $assets, User $actor): array
    {
        $checkedIn = 0;
        $alreadyCheckedIn = [];
        $failures = [];

        foreach ($assets as $asset) {
            if (empty($asset->assigned_to) || empty($asset->assigned_type)) {
                $alreadyCheckedIn[] = $asset->asset_tag ?: $asset->id;
                continue;
            }

            if (!$asset->model) {
                $failures[] = $asset->asset_tag ?: $asset->id;
                continue;
            }

            DB::transaction(function () use ($asset, $actor, &$checkedIn, &$failures) {
                $previousAssignee = $asset->assignedTo;
                $originalValues = $asset->getRawOriginal();
                $checkinAt = now();

                $this->migrateLegacyLocations($asset);

                $asset->expected_checkin = null;
                $asset->last_checkin = $checkinAt;
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

                event(new CheckoutableCheckedIn($asset, $previousAssignee, $actor, null, $checkinAt, $originalValues));
                $checkedIn++;
            });
        }

        return [
            'checked_in' => $checkedIn,
            'already_checked_in' => $alreadyCheckedIn,
            'failures' => $failures,
        ];
    }
}
