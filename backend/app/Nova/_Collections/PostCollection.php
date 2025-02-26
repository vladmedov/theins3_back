<?php

namespace App\Nova\_Collections;

use App\Nova\_Posts\Post;

use App\Enums\PostTypes;
use App\Enums\CategoryTypes;

class PostCollection extends Post {

    public static $model = 'App\Models\Post';

    public static $globallySearchable = false;

    public static $title = 'title';
    public static $search = ['id', 'title', 'slug'];

    public function subtitle()
    {
        $formattedDate = $this->published_at ? $this->published_at->format('d.m.Y H:i:s') : '';
        return "[" . PostTypes::get($this->type) . "] " . (CategoryTypes::isDefault($this->type) ? "{$this->category->title} / {$formattedDate}" : "{$formattedDate}");
    }
}
