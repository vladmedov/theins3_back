<?php

namespace App\Nova\_Posts;

use App\Enums\PostTypes;

class PostArticle extends Post {

    public static $model = \App\Models\PostTypes\PostArticle::class;

    public static function getPostType() {
        return PostTypes::ARTICLE;
    }

    public static function label() {
        return __('Articles');
    }

    public static function singularLabel() {
        return __('Article');
    }
}
