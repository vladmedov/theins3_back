<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PostTypes\PostOnline;
use App\Models\PostTypes\OnlineMessage;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnlineMessagePolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canViewAll()
            || PostOnline::checkAccessByRoles($user->roles);
    }

    public function update(User $user, OnlineMessage $onlineMessage)
    {
        return $user->canViewAll() 
            || ($post->isOwner($user->id) && PostOnline::checkAccessByRoles($user->roles));
    }

    public function delete(User $user, OnlineMessage $onlineMessage)
    {
        return $user->canDeleteAll()
            || ($post->isOwner($user->id) && PostOnline::checkAccessByRoles($user->roles))
            || ($post->isOwner($user->id) && $user->canViewAll());
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return $user->canViewAll() || PostOnline::checkAccessByRoles($user->roles);
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