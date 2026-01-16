<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class AssetCheckinController extends Controller
{
    public function create(Request $request, User $user): View | RedirectResponse
    {
        $this->authorize('view', $user);
        $this->authorize('checkin', Asset::class);

        $assets = Asset::with('assignedTo')
            ->where('assigned_type', User::class)
            ->where('assigned_to', $user->id)
            ->get();

        if ($assets->isEmpty()) {
            return redirect()->back()->with('error', trans('admin/users/message.user_has_no_assets_assigned'));
        }

        $assetTags = $assets->map(fn (Asset $asset) => $asset->asset_tag ?: $asset->id)->implode(', ');
        $backUrl = $request->headers->get('referer', route('users.show', $user));

        return view('users/bulk-checkin-assets', [
            'user' => $user,
            'assets' => $assets,
            'asset_tags' => $assetTags,
            'statusLabel_list' => Helper::statusLabelList(),
            'back_url' => $backUrl,
        ]);
    }

    public function store(Request $request, User $user, AssetCheckinService $checkinService): RedirectResponse
    {
        $this->authorize('view', $user);
        $this->authorize('checkin', Asset::class);

        $redirectUrl = $request->input('back_url', $request->headers->get('referer', route('users.show', $user)));

        $assetIds = $request->input('ids', []);

        $assets = Asset::with(['assignedTo', 'licenseseats'])
            ->when(! empty($assetIds), function ($query) use ($assetIds) {
                return $query->whereIn('id', $assetIds);
            })
            ->where('assigned_type', User::class)
            ->where('assigned_to', $user->id)
            ->get();

        if ($assets->isEmpty()) {
            return redirect()->to($redirectUrl)->with('error', trans('admin/users/message.user_has_no_assets_assigned'));
        }

        $results = $checkinService->checkinAssets(
            $assets,
            $request->user(),
            [
                'status_id' => $request->input('status_id'),
                'note' => $request->input('note'),
                'location_id' => $request->input('location_id'),
            ]
        );

        if ($results['checked_in'] === 0) {
            $message = trans('admin/hardware/message.bulk_checkin.error');

            if (! empty($results['already_checked_in'])) {
                $message .= ' ' . trans_choice(
                    'admin/hardware/message.bulk_checkin.already_checked_in',
                    count($results['already_checked_in']),
                    ['asset_tags' => implode(', ', $results['already_checked_in'])]
                );
            }

            if (! empty($results['failures'])) {
                $message .= ' ' . trans_choice(
                    'admin/hardware/message.bulk_checkin.failed',
                    count($results['failures']),
                    ['asset_tags' => implode(', ', $results['failures'])]
                );
            }

            return redirect()->to($redirectUrl)->with('error', $message);
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
