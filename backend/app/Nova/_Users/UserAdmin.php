<?php

namespace App\Nova\_Users;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class UserAdmin extends User
{
    public static function label() {
        return __('Admins');
    }

    public static function singularLabel() {
        return __('Admin');
    }

    protected static function getUserRole() {
        return UserRoles::ADMIN;
    }

    protected static function getPostType() {
        return false;
    }
}
