<?php

namespace App\Policies;

use App\Models\Termin;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerminPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user)
    {
        return true;
    }

    public function delete(User $user, Termin $termin)
    {
        return $user->canViewAll() && !$termin->isUsedInPosts();
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return true;
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