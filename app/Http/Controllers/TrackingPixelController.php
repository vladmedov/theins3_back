<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Post;

class TrackingPixelController extends Controller
{
    public function trackView($language_code, Request $request)
    {
        $postSlug = $request->input('post_slug');

        if (!$postSlug) {
            return response()->json(['status' => 'ERROR_INVALID_REQUEST'], 400);
        }

        $post = Post::select('id')
            ->where('slug', $postSlug)
            ->where('language_code', $language_code)
            ->where('status', Post::STATUS_PUBLISHED)
            ->first();

        if (!$post) {
            return response()->json(['status' => 'ERROR_POST_NOT_FOUND'], 404);
        }

        $redis = Redis::connection()->client();
        $redis->incr("view_count:{$post->id}");
        
        return response()->json(['status' => 'SUCCESS']);
    }
}

