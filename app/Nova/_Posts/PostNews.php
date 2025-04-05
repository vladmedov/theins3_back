<?php

namespace App\Nova\_Posts;

use App\Enums\PostTypes;

class PostNews extends Post {

    public static $model = \App\Models\PostTypes\PostNews::class;

    public static function getPostType() {
        return PostTypes::NEWS;
    }

    public static function label() {
        return __('News');
    }

    public static function singularLabel() {
        return __('News Entry');
    }
}
