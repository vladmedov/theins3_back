<?php

namespace App\Nova\_Collections;

class CollectionFeature extends Collection
{
    protected static function getCollectionType(): string {
        return 'feature';
    }
    
    public static function label() {
        return __('Feature');
    }

    public static function singularLabel() {
        return __('Feature record');
    }
}
