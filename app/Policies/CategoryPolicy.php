<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function view()
    {
        return false;
    }

    public function create(User $user)
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function update(User $user)
    {
        return $user->isAdmin() || $user->isEditor();
    }

    public function delete(User $user)
    {
        return $user->isAdmin();
    }

    public function restore()
    {
        return false;
    }

    public function replicate()
    {
        return false;
    }

    public function forceDelete()
    {
        return false;
    }
}