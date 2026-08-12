<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Asset;

class AssetPolicy extends CheckoutablePermissionsPolicy
{
    public function before(User $user, $ability, $item)
    {
        if (
            $ability === 'view'
            && $item instanceof Asset
            && $user->hasAccess('assets.view')
            && $item->isManagedBy($user)
        ) {
            return true;
        }

        return parent::before($user, $ability, $item);
    }

    protected function columnName()
    {
        return 'assets';
    }

    public function viewRequestable(User $user, Asset $asset = null)
    {
        return $user->hasAccess('assets.view.requestable');
    }

    public function audit(User $user, Asset $asset = null)
    {
        return $user->hasAccess('assets.audit');
    }
}
