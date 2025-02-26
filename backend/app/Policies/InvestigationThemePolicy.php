<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

use App\Enums\UserRoles;

class InvestigationThemePolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canViewAll() || $user->hasRole(UserRoles::AUTHOR);
    }

    public function update(User $user)
    {
        return $user->canViewAll() || $user->hasRole(UserRoles::AUTHOR);
    }

    public function delete(User $user)
    {
        return $user->canDeleteAll();
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return $user->canViewAll() || $user->hasRole(UserRoles::AUTHOR);
    }

    public function view()
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