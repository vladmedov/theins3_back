<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Tag;
use App\Models\Post;
use App\Http\Resources\TagResource;

class TagController extends Controller
{
    public function getTag($language_code, $slug)
    {
        $tag = Tag
            ::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist'])
                    ->where('status', Post::STATUS_PUBLISHED)
                    ->orderBy('published_at', 'desc')
                    ->paginate(36);
            }])
            ->where('language_code', $language_code)
            ->where('slug', $slug)
            ->firstOrFail();

        return new TagResource($tag, false);
    }
    
    public function getAllTags($language_code)
    {
        $tags = Tag
            ::where('language_code', $language_code)
            ->withCount(['posts' => function($query) {
                $query->where('status', Post::STATUS_PUBLISHED);
            }])
            ->get()
            ->filter(function($tag) {
                return $tag->posts_count > 0;
            })
            ->sortByDesc('posts_count')
            ->take(20) 
            ->values(); 

        return TagResource::collection($tags);
    }
}