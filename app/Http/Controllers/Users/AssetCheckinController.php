<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetCheckinController extends Controller
{
    public function store(Request $request, User $user, AssetCheckinService $checkinService): RedirectResponse
    {
        $this->authorize('view', $user);
        $this->authorize('checkin', Asset::class);

        $redirectUrl = $request->headers->get('referer', route('users.show', $user));

        $assets = Asset::with(['assignedTo', 'licenseseats'])
            ->where('assigned_type', User::class)
            ->where('assigned_to', $user->id)
            ->get();

        if ($assets->isEmpty()) {
            return redirect()->to($redirectUrl)->with('error', trans('admin/users/message.user_has_no_assets_assigned'));
        }

        $results = $checkinService->checkinAssets($assets, $request->user());

        if ($results['checked_in'] === 0) {
            return redirect()->to($redirectUrl)->with('error', trans('admin/hardware/message.bulk_checkin.error'));
        }

        $message = trans_choice('admin/users/message.success.checkin', $results['checked_in'], ['count' => $results['checked_in']]);

        if (! empty($results['failures'])) {
            $message .= ' ' . trans_choice(
                'admin/hardware/message.bulk_checkin.failed',
                count($results['failures']),
                ['asset_tags' => implode(', ', $results['failures'])]
            );
        }

        return redirect()->to($redirectUrl)->with('success', $message);
    }
}
