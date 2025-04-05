<?php

namespace App\Nova\_Posts;

use App\Enums\PostTypes;

class PostOpinion extends Post {

    public static $model = \App\Models\PostTypes\PostOpinion::class;

    public static function getPostType() {
        return PostTypes::OPINION;
    }

    public static function label() {
        return __('Opinions');
    }

    public static function singularLabel() {
        return __('Opinion');
    }
}
