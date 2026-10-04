<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;

class PlatformDongles
{
    public static function isPlatformDongleModel(?AssetModel $model): bool
    {
        if (! $model) {
            return false;
        }

        return in_array(mb_strtolower($model->name), self::modelNames(), true);
    }

    public static function normalizeAsset(Asset $asset): void
    {
        if (! self::isPlatformDongleModel($asset->model)) {
            return;
        }

        // The Asset Model is the physical product identity for a dongle.
        $asset->name = null;
        $asset->location_id = null;
        $asset->rtd_location_id = null;
    }

    public static function licenseForLinking(?int $licenseId): ?License
    {
        if (! $licenseId) {
            return null;
        }

        return License::find($licenseId);
    }

    public static function canLinkLicense(Asset $asset, ?License $license): ?string
    {
        if (! self::isPlatformDongleModel($asset->model)) {
            return null;
        }

        if (! $license) {
            return 'A license entitlement is required for a Platform dongle.';
        }

        if ($asset->licenses()->where('licenses.id', $license->id)->exists()) {
            return null;
        }

        if ($asset->licenses()->exists()) {
            return 'A Platform dongle can be linked to only one license entitlement. Check in its current license before selecting another.';
        }

        if ($license->isInactive()) {
            return 'The selected license entitlement is inactive.';
        }

        if (! $license->freeSeat()) {
            return 'The selected license entitlement has no available seat to link to this dongle.';
        }

        return null;
    }

    public static function linkLicense(Asset $asset, License $license, User $actor): void
    {
        if (! self::isPlatformDongleModel($asset->model) || $asset->licenses()->where('licenses.id', $license->id)->exists()) {
            return;
        }

        $seat = $license->freeSeat();
        if (! $seat) {
            throw new \LogicException('The selected license entitlement has no available seat to link to this dongle.');
        }

        $seat->asset_id = $asset->id;
        $seat->assigned_to = $asset->assigned_to;
        $seat->created_by = $actor->id;
        $seat->save();
    }

    public static function existingLicenseId(Asset $asset): ?int
    {
        return $asset->licenses()->value('licenses.id');
    }

    public static function modelNames(): array
    {
        $modelNames = config('leams.platform_dongle_model_names');

        // A cached configuration created before this setting existed should
        // still recognize the standard Platform dongles until it is rebuilt.
        if (empty($modelNames)) {
            $modelNames = ['Vector KEYMAN', 'SIEMENS DONGLE'];
        }

        return array_map('mb_strtolower', $modelNames);
    }
}
