<?php

namespace App\Nova\Metrics\Traits;

use App\Models\Post;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

trait PostFilterTrait
{
    public function postFilter($postType)
    {
        $query = Post::query();
        $query->where('language_code', app()->getLocale());
        if ($postType) {
            $query->where('type', $postType);
        }

        return $query;
    }
}
