<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Http\Request;

use Laravel\Nova\Nova;
use Laravel\Nova\Http\Requests\NovaRequest;

use App\Models\User;
use App\Models\PostTypes\PostOnline;
use App\Enums\PostTypes;

class PostOnlinePolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canViewAll()
            || PostOnline::checkAccessByRoles($user->roles);
    }

    public function update(User $user, PostOnline $post)
    {
        $hasAccess = $user->canViewAll() 
            || ($post->isOwner($user->id) && PostOnline::checkAccessByRoles($user->roles));
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::ONLINE && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function delete(User $user, PostOnline $post)
    {
        $hasAccess = $user->canDeleteAll()
            || ($post->isOwner($user->id) && PostOnline::checkAccessByRoles($user->roles))
            || ($post->isOwner($user->id) && $user->canViewAll());
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::ONLINE && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return $user->canViewAll() || PostOnline::checkAccessByRoles($user->roles);
    }

    public function view(User $user, PostOnline $post)
    {
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::ONLINE,
            fn(Request $request) => true
        );
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