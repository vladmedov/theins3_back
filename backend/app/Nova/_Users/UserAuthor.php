<?php

namespace App\Nova\_Users;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class UserAuthor extends User
{
    public static function label() {
        return __('Authors');
    }

    public static function singularLabel() {
        return __('Author');
    }
    
    public function getPostsCountTitle() {
        return __('Articles count');
    }

    public function getPostsCount() {
        return $this->articles()->count();
    }

    protected static function getUserRole() {
        return UserRoles::AUTHOR;
    }

    protected static function getPostType() {
        return PostTypes::ARTICLE;
    }
}
