<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetCheckinController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $this->authorize('view', $user);
        $this->authorize('checkin', Asset::class);

        $assetTags = Asset::where('assigned_type', User::class)
            ->where('assigned_to', $user->id)
            ->pluck('asset_tag')
            ->filter()
            ->values()
            ->all();

        if (empty($assetTags)) {
            return redirect()->route('users.show', $user)->with('error', trans('admin/users/message.user_has_no_assets_assigned'));
        }

        session([
            'bulk_checkin_asset_tags' => $assetTags,
            'bulk_checkin_back_url' => route('users.show', $user),
        ]);

        return redirect()->route('hardware/quickscancheckin');
    }
}
