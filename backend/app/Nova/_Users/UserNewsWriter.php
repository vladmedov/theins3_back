<?php

namespace App\Nova\_Users;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class UserNewsWriter extends User
{
    public static function label() {
        return __('News writers');
    }

    public static function singularLabel() {
        return __('News writer');
    }
    
    public function getPostsCountTitle() {
        return __('News count');
    }

    public function getPostsCount() {
        return $this->news()->count();
    }

    protected static function getUserRole() {
        return UserRoles::NEWS_WRITER;
    }

    protected static function getPostType() {
        return PostTypes::NEWS;
    }
}
