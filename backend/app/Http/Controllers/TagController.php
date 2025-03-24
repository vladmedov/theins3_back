<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Tag;
use App\Http\Resources\TagResource;

class TagController extends Controller
{
    public function getTag($language_code, $slug)
    {
        $tag = Tag
            ::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist'])->paginate(36);
            }])
            ->where('language_code', $language_code)
            ->where('slug', $slug)
            ->firstOrFail();

        return new TagResource($tag, false);
    }
}