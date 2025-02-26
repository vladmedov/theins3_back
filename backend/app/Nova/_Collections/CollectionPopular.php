<?php

namespace App\Nova\_Collections;

use App\Enums\PostTypes;

class CollectionPopular extends Collection
{
    protected static function getCollectionType(): string {
        return 'popular';
    }

    protected static function filterPostType(): string {
        return PostTypes::ARTICLE;
    }
    
    public static function label() {
        return __('Popular');
    }

    public static function singularLabel() {
        return __('Popular record');
    }
}
