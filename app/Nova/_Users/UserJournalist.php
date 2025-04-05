<?php

namespace App\Nova\_Users;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class UserJournalist extends User
{
    public static function label() {
        return __('Journalists');
    }

    public static function singularLabel() {
        return __('Journalist');
    }

    protected static function getUserRole() {
        return UserRoles::JOURNALIST;
    }

    protected static function getPostType() {
        return false;
    }
}
