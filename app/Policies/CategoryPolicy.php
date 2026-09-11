<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'categories';
    }

    public function index(User $user)
    {
        if ($this->permissionState($user, 'view') === 'deny') {
            return false;
        }

        return $this->permissionState($user, 'view') === 'allow'
            || Category::managedBy($user)->exists();
    }

    public function view(User $user, $item = null)
    {
        if (! $item instanceof Category) {
            return $this->index($user);
        }

        if ($this->permissionState($user, 'view') === 'deny') {
            return false;
        }

        if ($user->hasCategoryOwnershipScope()) {
            return $item->isManagedBy($user);
        }

        return $this->permissionState($user, 'view') === 'allow';
    }

    public function create(User $user)
    {
        return $this->permissionState($user, 'create') === 'allow'
            && ! $user->hasCategoryOwnershipScope();
    }

    public function update(User $user, $item = null)
    {
        if ($this->permissionState($user, 'edit') !== 'allow') {
            return false;
        }

        if ($item instanceof Category && $user->hasCategoryOwnershipScope()) {
            return $item->isManagedBy($user);
        }

        return true;
    }

    public function delete(User $user, $item = null)
    {
        if ($this->permissionState($user, 'delete') !== 'allow') {
            return false;
        }

        if ($item instanceof Category && $user->hasCategoryOwnershipScope()) {
            return empty($item->deleted_at) && $item->isManagedBy($user);
        }

        return ! $item instanceof Category || empty($item->deleted_at);
    }

    protected function permissionState(User $user, string $ability): string
    {
        $permission = $this->columnName().'.'.$ability;

        if ($user->hasAccess($permission)) {
            return 'allow';
        }

        $permissions = $user->decodePermissions();

        if (is_array($permissions) && (($permissions[$permission] ?? null) === -1)) {
            return 'deny';
        }

        return 'inherit';
    }
}
