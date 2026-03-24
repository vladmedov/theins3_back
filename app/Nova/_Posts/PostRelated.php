<?php

namespace App\Nova\_Posts;

use Illuminate\Http\Request;

class PostRelated extends Post
{
    public static $model = \App\Models\Post::class;

    public static function authorizable(): bool
    {
        return false;
    }

    public static function availableForNavigation(Request $request): bool
    {
        return false;
    }
}
