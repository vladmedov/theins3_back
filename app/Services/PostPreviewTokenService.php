<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PostPreviewTokenService
{
    public const CACHE_PREFIX = 'post_preview:';
    public const TTL_MINUTES = 60;

    /**
     * Create a temporary preview token for a post. Valid for 1 hour.
     */
    public function createToken(Post $post): string
    {
        $token = Str::random(64);
        Cache::put(self::CACHE_PREFIX . $token, $post->id, now()->addMinutes(self::TTL_MINUTES));
        return $token;
    }

    /**
     * Validate token and return post id or null.
     */
    public function validateToken(string $token): ?int
    {
        $postId = Cache::get(self::CACHE_PREFIX . $token);
        return $postId ? (int) $postId : null;
    }
}
