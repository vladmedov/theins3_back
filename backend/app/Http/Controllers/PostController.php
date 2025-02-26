<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Post;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    public function getById($postId)
    {
        $post = Post::with([
            'category',
            'investigationTheme',
            'translation',
            'translation.category',
            'translation.authors',
            'translation.columnist',
            'tags',
            'termins',
            'authors',
            'columnist',
        ])->findOrFail($postId);

        dd((new PostResource($post))->toArray(request()));

        return new PostResource($post);    
    }
}