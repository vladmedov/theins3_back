<?php

namespace App\Nova\_Posts;

use App\Enums\PostTypes;

class PostOnline extends Post {

    public static $model = \App\Models\PostTypes\PostOnline::class;

    public static function getPostType() {
        return PostTypes::ONLINE;
    }

    public static function label() {
        return __('Onlines');
    }

    public static function singularLabel() {
        return __('Online');
    }
}
