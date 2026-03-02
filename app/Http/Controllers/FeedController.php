<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use App\Models\Post;
use App\Services\ContentRenderer;

class FeedController extends Controller
{
    private const CACHE_TTL = 600; // 10 minutes
    private const STANDARD_LIMIT = 50;
    private const YANDEX_NEWS_DAYS = 7;

    public function rss(string $language_code = 'ru'): Response
    {
        $content = Cache::remember("feed:rss:{$language_code}", self::CACHE_TTL, function () use ($language_code) {
            $posts = $this->getLatestPosts($language_code, self::STANDARD_LIMIT);

            return view('feeds.rss', [
                'posts' => $posts,
                'language' => $language_code,
                'siteUrl' => $this->getSiteUrl(),
                'selfUrl' => $this->getSiteUrl() . $this->feedPath($language_code, ''),
                'description' => $this->getSiteDescription($language_code),
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    public function yandexNews(string $language_code = 'ru'): Response
    {
        $content = Cache::remember("feed:yandex-news:{$language_code}", self::CACHE_TTL, function () use ($language_code) {
            $posts = Post::where('status', Post::STATUS_PUBLISHED)
                ->where('language_code', $language_code)
                ->where('published_at', '>=', now()->subDays(self::YANDEX_NEWS_DAYS))
                ->with(['category', 'authors', 'columnist'])
                ->orderBy('published_at', 'desc')
                ->limit(1000)
                ->get();

            return view('feeds.yandex-news', [
                'posts' => $posts,
                'language' => $language_code,
                'siteUrl' => $this->getSiteUrl(),
                'selfUrl' => $this->getSiteUrl() . $this->feedPath($language_code, '/yandex-news'),
                'description' => $this->getSiteDescription($language_code),
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    public function dzen(string $language_code = 'ru'): Response
    {
        $content = Cache::remember("feed:dzen:{$language_code}", self::CACHE_TTL, function () use ($language_code) {
            $posts = $this->getLatestPosts($language_code, self::STANDARD_LIMIT);

            return view('feeds.dzen', [
                'posts' => $posts,
                'language' => $language_code,
                'siteUrl' => $this->getSiteUrl(),
                'selfUrl' => $this->getSiteUrl() . $this->feedPath($language_code, '/dzen'),
                'description' => $this->getSiteDescription($language_code),
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    public function googleNews(string $language_code = 'ru'): Response
    {
        $content = Cache::remember("feed:google-news:{$language_code}", self::CACHE_TTL, function () use ($language_code) {
            $posts = $this->getLatestPosts($language_code, self::STANDARD_LIMIT);

            return view('feeds.google-news', [
                'posts' => $posts,
                'language' => $language_code,
                'siteUrl' => $this->getSiteUrl(),
                'selfUrl' => $this->getSiteUrl() . $this->feedPath($language_code, '/google-news'),
                'description' => $this->getSiteDescription($language_code),
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    public function facebookInstant(string $language_code = 'ru'): Response
    {
        $content = Cache::remember("feed:facebook-instant:{$language_code}", self::CACHE_TTL, function () use ($language_code) {
            $posts = $this->getLatestPosts($language_code, self::STANDARD_LIMIT);

            return view('feeds.facebook-instant', [
                'posts' => $posts,
                'language' => $language_code,
                'siteUrl' => $this->getSiteUrl(),
                'selfUrl' => $this->getSiteUrl() . $this->feedPath($language_code, '/facebook-instant'),
                'description' => $this->getSiteDescription($language_code),
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    private function getLatestPosts(string $languageCode, int $limit)
    {
        return Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $languageCode)
            ->with(['category', 'authors', 'columnist'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function xmlResponse(string $content): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ltrim($content, "\r\n ");
        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function getSiteUrl(): string
    {
        return rtrim(config('app.site_url'), '/');
    }

    private function getSiteDescription(string $lang): string
    {
        return match ($lang) {
            'ru' => 'The Insider — расследования, аналитика, мнения',
            'en' => 'The Insider — investigations, analysis, opinions',
            default => 'The Insider',
        };
    }

    private function feedPath(string $lang, string $suffix): string
    {
        $lang = $lang ?: 'ru';
        return "/{$lang}/feed" . $suffix;
    }
}
