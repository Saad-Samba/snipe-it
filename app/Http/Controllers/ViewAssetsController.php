<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * This controller handles the personal asset view for a user and, when enabled,
 * the manager-subordinate view of that same page.
 */
class ViewAssetsController extends Controller
{
    /**
     * Extract custom fields that should be displayed in user view.
     */
    private function extractCustomFields(User $user): array
    {
        $fieldArray = [];
        foreach ($user->assets as $asset) {
            if ($asset->model && $asset->model->fieldset) {
                foreach ($asset->model->fieldset->fields as $field) {
                    if ($field->display_in_user_view == '1') {
                        $fieldArray[$field->db_column] = $field->name;
                    }
                }
            }
        }

        return array_unique($fieldArray);
    }

    /**
     * Get list of users viewable by the current user.
     */
    private function getViewableUsers(User $authUser): \Illuminate\Support\Collection
    {
        if ($authUser->isSuperUser()) {
            return User::select('id', 'first_name', 'last_name', 'username')
                ->where('activated', 1)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        $managedUsers = $authUser->getAllSubordinates();

        if ($managedUsers->count() > 0) {
            return collect([$authUser])->merge($managedUsers)
                ->sortBy('last_name')
                ->sortBy('first_name');
        }

        return collect([$authUser]);
    }

    /**
     * Get the selected user ID from request or default to current user.
     */
    private function getSelectedUserId(Request $request, \Illuminate\Support\Collection $subordinates, int $defaultUserId): int
    {
        if ($subordinates->count() <= 1 || ! $request->filled('user_id')) {
            return $defaultUserId;
        }

        $requestedUserId = (int) $request->input('user_id');

        if ($subordinates->contains('id', $requestedUserId)) {
            return $requestedUserId;
        }

        return $defaultUserId;
    }

    /**
     * Show user's assigned assets with optional manager view functionality.
     */
    public function getIndex(Request $request): View|RedirectResponse
    {
        $authUser = auth()->user();
        $settings = Setting::getSettings();
        $subordinates = collect();
        $selectedUserId = $authUser->id;

        if ($settings->manager_view_enabled) {
            $subordinates = $this->getViewableUsers($authUser);
            $selectedUserId = $this->getSelectedUserId($request, $subordinates, $authUser->id);
        }

        $userToView = User::with([
            'assets',
            'assets.model',
            'assets.model.fieldset.fields',
            'consumables',
            'accessories',
            'licenses',
        ])->find($selectedUserId);

        if (! $userToView) {
            return redirect()->route('view-assets')->with('error', trans('admin/users/message.user_not_found'));
        }

        $fieldArray = $this->extractCustomFields($userToView);

        return view('account/view-assets', [
            'user' => $userToView,
            'field_array' => $fieldArray,
            'settings' => $settings,
            'subordinates' => $subordinates,
            'selectedUserId' => $selectedUserId,
        ]);
    }
}
