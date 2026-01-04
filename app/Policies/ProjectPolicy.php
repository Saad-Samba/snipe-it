<?php

namespace App\Policies;

use App\Models\User;

class ProjectPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'projects';
    }

    public function index(User $user)
    {
        return $user->isSuperUser();
    }

    public function view(User $user, $item = null)
    {
        return $user->isSuperUser();
    }

    public function create(User $user)
    {
        return $user->isSuperUser();
    }

    public function update(User $user, $item = null)
    {
        return $user->isSuperUser();
    }

    public function delete(User $user, $item = null)
    {
        $itemConditional = true;
        if ($item) {
            $itemConditional = empty($item->deleted_at);
        }

        return $itemConditional && $user->isSuperUser();
    }
}
