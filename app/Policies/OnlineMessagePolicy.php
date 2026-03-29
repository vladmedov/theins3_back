<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PostTypes\OnlineMessage;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnlineMessagePolicy
{
    use HandlesAuthorization;

    /**
     * Доступ к сообщению онлайна: админ и редактор — ко всем; журналист — только если есть доступ к родительскому онлайну (владелец).
     */
    protected function userHasAccessToParentOnline(User $user, OnlineMessage $onlineMessage): bool
    {
        if ($user->canViewAll()) {
            return true;
        }

        $post = $onlineMessage->online;

        return $post && $post->isOwner($user->id);
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, OnlineMessage $onlineMessage)
    {
        return $this->userHasAccessToParentOnline($user, $onlineMessage);
    }

    public function delete(User $user, OnlineMessage $onlineMessage)
    {
        return $this->userHasAccessToParentOnline($user, $onlineMessage);
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