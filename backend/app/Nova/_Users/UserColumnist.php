<?php

namespace App\Nova\_Users;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class UserColumnist extends User
{
    public static function label() {
        return __('Columnists');
    }

    public static function singularLabel() {
        return __('Columnist');
    }
    
    public function getPostsCountTitle() {
        return __('Opinions count');
    }

    public function getPostsCount() {
        return $this->opinions()->count();
    }

    protected static function getUserRole() {
        return UserRoles::COLUMNIST;
    }

    protected static function getPostType() {
        return PostTypes::OPINION;
    }
}
