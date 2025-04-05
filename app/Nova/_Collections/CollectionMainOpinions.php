<?php

namespace App\Nova\_Collections;

use App\Enums\PostTypes;

class CollectionMainOpinions extends Collection
{
    protected static function getCollectionType(): string {
        return 'main_opinions';
    }

    protected static function filterPostType(): string {
        return PostTypes::OPINION;
    }
    
    public static function label() {
        return __('Opinions on main');
    }

    public static function singularLabel() {
        return __('Opinion record on main');
    }
}