<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Post;
use App\Models\PostTypes\PostArticle;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canViewAll()
            || PostArticle::checkAccessByRoles($user->roles);
    }

    public function update(User $user, PostArticle $post)
    {
        return $user->canViewAll() 
            || ($post->isOwner($user->id) && PostArticle::checkAccessByRoles($user->roles));
    }

    public function delete(User $user, PostArticle $post)
    {
        return $user->canDeleteAll()
            || ($post->isOwner($user->id) && PostArticle::checkAccessByRoles($user->roles))
            || ($post->isOwner($user->id) && $user->canViewAll());
    }

    public function restore()
    {
        return false;
    }

    public function viewAny(User $user)
    {
        return false;
        return $user->canViewAll() || PostArticle::checkAccessByRoles($user->roles);
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