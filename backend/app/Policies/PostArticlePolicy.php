<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Http\Request;

use Laravel\Nova\Nova;
use Laravel\Nova\Http\Requests\NovaRequest;

use App\Models\User;
use App\Models\PostTypes\PostArticle;
use App\Enums\PostTypes;

class PostArticlePolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canViewAll()
            || PostArticle::checkAccessByRoles($user->roles);
    }

    public function update(User $user, PostArticle $post)
    {
        $hasAccess = $user->canViewAll() 
            || ($post->isOwner($user->id) && PostArticle::checkAccessByRoles($user->roles));
        
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::ARTICLE && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function delete(User $user, PostArticle $post)
    {
        $hasAccess = $user->canDeleteAll()
            || ($post->isOwner($user->id) && PostArticle::checkAccessByRoles($user->roles))
            || ($post->isOwner($user->id) && $user->canViewAll());
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::ARTICLE && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return $user->canViewAll() || PostArticle::checkAccessByRoles($user->roles);
    }

    public function view(User $user, PostArticle $post)
    {
        $hasAccess = $user->canViewAll() || PostArticle::checkAccessByRoles($user->roles);
        
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::ARTICLE && $hasAccess,
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