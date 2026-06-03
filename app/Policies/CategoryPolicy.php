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
        return match ($this->permissionState($user, 'view')) {
            'allow' => true,
            'deny' => false,
            default => Category::managedBy($user)->exists(),
        };
    }

    public function view(User $user, $item = null)
    {
        if (! $item instanceof Category) {
            return $this->index($user);
        }

        return match ($this->permissionState($user, 'view')) {
            'allow' => true,
            'deny' => false,
            default => $item->isManagedBy($user),
        };
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
