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
        return true;
    }

    public function update(User $user, OnlineMessage $onlineMessage)
    {
        return true;
    }

    public function delete(User $user, OnlineMessage $onlineMessage)
    {
        return true;
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