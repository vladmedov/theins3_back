<?php

namespace App\Nova\_Users;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class UserEditor extends User
{
    public static function label() {
        return __('Editors');
    }

    public static function singularLabel() {
        return __('Editor');
    }

    protected static function getUserRole() {
        return UserRoles::EDITOR;
    }

    protected static function getPostType() {
        return false;
    }
}
