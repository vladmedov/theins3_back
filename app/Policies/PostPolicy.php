<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Post;
use App\Models\PostTypes\PostArticle;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return false;
    }

    public function create(User $user)
    {
        return true;
    }

    public function view()
    {
        return false;
    }

    public function update(User $user, PostArticle $post)
    {
        return $user->isAdmin() || $user->isEditor() || $post->isOwner($user->id);
    }

    public function delete(User $user, PostArticle $post)
    {
        return $user->isAdmin() || $post->isOwner($user->id);
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