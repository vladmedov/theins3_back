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
        $ip = $request->input('ip');
        
        if (!$postSlug || !$ip) {
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
        
        $ipKey = "view_ip:{$post->id}:{$ip}";
        
        if ($redis->exists($ipKey)) {
            return response()->json(['status' => 'ERROR_IP_LIMIT']);
        }
        
        $redis->setex($ipKey, 3600, '1');
        
        $redis->incr("view_count:{$post->id}");
        
        return response()->json(['status' => 'SUCCESS']);
    }
}

