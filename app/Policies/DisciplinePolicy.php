<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DisciplinePolicy
{
    use HandlesAuthorization;

    public function index(User $user)
    {
        return $user->isSuperUser();
    }

    public function view(User $user, $discipline = null)
    {
        return $user->isSuperUser();
    }

    public function create(User $user)
    {
        return $user->isSuperUser();
    }

    public function update(User $user, $discipline = null)
    {
        return $user->isSuperUser();
    }

    public function delete(User $user, $discipline = null)
    {
        return $user->isSuperUser();
    }
}
