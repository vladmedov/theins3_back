<?php

namespace App\Nova\_Collections;

use App\Enums\PostTypes;

class CollectionTopNews extends Collection
{
    protected static function getCollectionType(): string {
        return 'top_news';
    }

    protected static function filterPostType(): string {
        return PostTypes::NEWS;
    }
    
    public static function label() {
        return __('Top News');
    }

    public static function singularLabel() {
        return __('Top News record');
    }
}

