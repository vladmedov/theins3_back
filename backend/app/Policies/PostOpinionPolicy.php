<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Http\Request;

use Laravel\Nova\Nova;
use Laravel\Nova\Http\Requests\NovaRequest;

use App\Models\User;
use App\Models\PostTypes\PostOpinion;
use App\Enums\PostTypes;

class PostOpinionPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canViewAll()
            || PostOpinion::checkAccessByRoles($user->roles);
    }

    public function update(User $user, PostOpinion $post)
    {
        $hasAccess = $user->canViewAll() 
            || ($post->isOwner($user->id) && PostOpinion::checkAccessByRoles($user->roles));
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::OPINION && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function delete(User $user, PostOpinion $post)
    {
        $hasAccess = $user->canDeleteAll()
            || ($post->isOwner($user->id) && PostOpinion::checkAccessByRoles($user->roles))
            || ($post->isOwner($user->id) && $user->canViewAll());
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::OPINION && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return $user->canViewAll() || PostOpinion::checkAccessByRoles($user->roles);
    }

    public function view(User $user, PostOpinion $post)
    {
        $hasAccess = $user->canViewAll() || PostOpinion::checkAccessByRoles($user->roles);
        
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::OPINION && $hasAccess,
            fn(Request $request) => $hasAccess
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