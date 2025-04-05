<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Http\Request;

use Laravel\Nova\Nova;
use Laravel\Nova\Http\Requests\NovaRequest;

use App\Models\User;
use App\Models\PostTypes\PostNews;
use App\Enums\PostTypes;

class PostNewsPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function create(User $user)
    {
        return true;
    }

    public function view(User $user, PostNews $post)
    {
        $hasAccess = $user->isAdmin() || $user->isEditor() || $post->isOwner($user->id);
        
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::NEWS && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function update(User $user, PostNews $post)
    {
        $hasAccess = $user->isAdmin() || $user->isEditor() || $post->isOwner($user->id);
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::NEWS && $hasAccess,
            fn(Request $request) => $hasAccess
        );
    }

    public function delete(User $user, PostNews $post)
    {
        $hasAccess = $user->isAdmin() || $post->isOwner($user->id);
            
        return Nova::whenServing(
            fn(NovaRequest $request) => $post->type === PostTypes::NEWS && $hasAccess,
            fn(Request $request) => $hasAccess
        );
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