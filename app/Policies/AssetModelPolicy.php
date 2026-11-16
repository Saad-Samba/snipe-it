<?php

namespace App\Policies;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;

class AssetModelPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'models';
    }

    public function view(User $user, $item = null)
    {
        if (! $user->hasAccess($this->columnName().'.view')) {
            return false;
        }

        if (! $item instanceof AssetModel) {
            return true;
        }

        return $item->isManagedBy($user);
    }

    public function request(User $user, AssetModel $item)
    {
        return $user->hasAccess($this->columnName().'.request')
            && empty($item->deleted_at);
    }

    public function create(User $user)
    {
        return $user->hasAccess($this->columnName().'.create')
            && Category::where('category_type', 'asset')
                ->where('manager_id', $user->id)
                ->exists();
    }

    public function update(User $user, $item = null)
    {
        if (! $user->hasAccess($this->columnName().'.edit')) {
            return false;
        }

        if (! $item instanceof AssetModel) {
            return true;
        }

        return $item->isManagedBy($user);
    }

    public function delete(User $user, $item = null)
    {
        if (! $user->hasAccess($this->columnName().'.delete')) {
            return false;
        }

        if (! $item instanceof AssetModel) {
            return true;
        }

        return empty($item->deleted_at) && $item->isManagedBy($user);
    }
}
