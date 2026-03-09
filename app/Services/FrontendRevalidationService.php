<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrontendRevalidationService
{
    protected static array $queuedTags = [];

    protected static array $queuedPostChanges = [];

    protected static bool $flushRegistered = false;

    public function queueTags(array $tags): void
    {
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                static::$queuedTags[$tag] = true;
            }
        }

        $this->registerFlush();
    }

    public function queuePostChange(int $postId, ?array $beforeSnapshot = null): void
    {
        static::$queuedPostChanges[$postId] ??= [];

        if ($beforeSnapshot) {
            static::$queuedPostChanges[$postId][] = $beforeSnapshot;
        }

        $this->registerFlush();
    }

    protected function registerFlush(): void
    {
        if (static::$flushRegistered) {
            return;
        }

        static::$flushRegistered = true;

        app()->terminating(function () {
            app(self::class)->flush();
        });
    }

    public function flush(): void
    {
        $tagService = app(FrontendCacheTagService::class);

        foreach (static::$queuedPostChanges as $postId => $beforeSnapshots) {
            $post = Post::query()
                ->with([
                    'category:id,slug',
                    'tags:id,slug',
                    'authors:id,slug',
                    'columnist:id,slug',
                    'investigationTheme:id,slug',
                ])
                ->find($postId);

            $currentSnapshot = $tagService->snapshotPost($post);

            foreach ($beforeSnapshots as $beforeSnapshot) {
                $this->queueTags($tagService->tagsForPostSnapshot($beforeSnapshot));
                $this->queueTags($this->tagsForCategoryChange($beforeSnapshot, $currentSnapshot));
            }

            $this->queueTags($tagService->tagsForPostSnapshot($currentSnapshot));
        }

        $tags = array_keys(static::$queuedTags);

        static::$queuedTags = [];
        static::$queuedPostChanges = [];
        static::$flushRegistered = false;

        if (empty($tags)) {
            return;
        }

        $url = config('services.frontend_revalidation.url');
        $secret = config('services.frontend_revalidation.secret');
        $timeout = (int) config('services.frontend_revalidation.timeout', 10);

        if (empty($url) || empty($secret)) {
            Log::warning('Frontend revalidation skipped: endpoint or secret is not configured.', [
                'tags' => $tags,
            ]);

            return;
        }

        try {
            $response = Http::timeout($timeout)
                ->asJson()
                ->withHeaders([
                    'x-revalidate-secret' => $secret,
                ])
                ->post($url, [
                    'tags' => $tags,
                ]);

            if (!$response->successful()) {
                Log::warning('Frontend revalidation request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'tags' => $tags,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Frontend revalidation request threw an exception.', [
                'message' => $exception->getMessage(),
                'tags' => $tags,
            ]);
        }
    }

    protected function tagsForCategoryChange(?array $beforeSnapshot, ?array $currentSnapshot): array
    {
        $beforeCategory = $beforeSnapshot['category_slug'] ?? null;
        $currentCategory = $currentSnapshot['category_slug'] ?? null;

        if ($beforeCategory === $currentCategory) {
            return [];
        }

        $tags = [];

        if (!empty($beforeSnapshot['lang'])) {
            $tags[] = 'layout:' . $beforeSnapshot['lang'];
        }

        if (!empty($currentSnapshot['lang'])) {
            $tags[] = 'layout:' . $currentSnapshot['lang'];
        }

        return array_values(array_unique($tags));
    }
}
