<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use App\Models\Post;
use App\Services\ContentRenderer;

class FeedController extends Controller
{
    private const CACHE_TTL = 60; // 1 minute
    private const STANDARD_LIMIT = 50;
    private const YANDEX_NEWS_DAYS = 7;
    private const RSS_PUBLISH_DELAY_MINUTES = 5;

    public function rss(string $language_code = 'ru'): Response
    {
        $content = Cache::remember("feed:rss:{$language_code}", self::CACHE_TTL, function () use ($language_code) {
            $posts = $this->getLatestPosts(
                $language_code,
                self::STANDARD_LIMIT,
                self::RSS_PUBLISH_DELAY_MINUTES
            );

            return view('feeds.rss', [
                'posts' => $posts,
                'language' => $language_code,
                'siteUrl' => $this->getSiteUrl($language_code),
                'selfUrl' => $this->getSiteUrl($language_code) . $this->feedPath($language_code, ''),
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
                'siteUrl' => $this->getSiteUrl($language_code),
                'selfUrl' => $this->getSiteUrl($language_code) . $this->feedPath($language_code, '/yandex-news'),
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
                'siteUrl' => $this->getSiteUrl($language_code),
                'selfUrl' => $this->getSiteUrl($language_code) . $this->feedPath($language_code, '/dzen'),
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
                'siteUrl' => $this->getSiteUrl($language_code),
                'selfUrl' => $this->getSiteUrl($language_code) . $this->feedPath($language_code, '/google-news'),
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
                'siteUrl' => $this->getSiteUrl($language_code),
                'selfUrl' => $this->getSiteUrl($language_code) . $this->feedPath($language_code, '/facebook-instant'),
                'description' => $this->getSiteDescription($language_code),
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    public function export(Request $request, string $language_code = 'ru'): Response
    {
        $perPageDefault = (int) config('feed.export_per_page_default', 50);
        $perPageMax     = (int) config('feed.export_per_page_max', 200);

        $perPage = max(1, min($perPageMax, (int) $request->query('per_page', $perPageDefault)));
        $page    = max(1, (int) $request->query('page', 1));

        $cacheKey = "feed:export:{$language_code}:p{$page}:pp{$perPage}";
        $ttl      = (int) config('feed.export_cache_ttl', 300);

        $content = Cache::remember($cacheKey, $ttl, function () use ($language_code, $page, $perPage, $perPageDefault) {
            [$posts, $totalPages, $page] = $this->getExportPosts($language_code, $page, $perPage);

            $perPageInUrl = $perPage !== $perPageDefault ? $perPage : null;
            $pageUrl = fn (int $p) =>
                $this->getSiteUrl($language_code)
                . $this->feedPath($language_code, '/export', $p, $perPageInUrl);

            return view('feeds.rss-export', [
                'posts'       => $posts,
                'language'    => $language_code,
                'siteUrl'     => $this->getSiteUrl($language_code),
                'selfUrl'     => $pageUrl($page),
                'description' => $this->getSiteDescription($language_code),
                'page'        => $page,
                'perPage'     => $perPage,
                'totalPages'  => $totalPages,
                'pageUrl'     => $pageUrl,
            ])->render();
        });

        return $this->xmlResponse($content);
    }

    private function getLatestPosts(string $languageCode, int $limit, ?int $publishedBeforeMinutes = null)
    {
        $query = Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $languageCode);

        if ($publishedBeforeMinutes !== null) {
            $query->where('published_at', '<=', now()->subMinutes($publishedBeforeMinutes));
        }

        return $query
            ->with(['category', 'authors', 'columnist'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getExportPosts(string $languageCode, int $page, int $perPage): array
    {
        $base = Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $languageCode);

        $total      = (clone $base)->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);

        $posts = $base
            ->with(['category', 'authors', 'columnist'])
            ->orderBy('published_at')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        return [$posts, $totalPages, $page];
    }

    private function xmlResponse(string $content): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ltrim($content, "\r\n ");
        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function getSiteUrl(string $lang): string
    {
        $host = $lang === 'en'
            ? config('app.en_canonical_host')
            : config('app.ru_canonical_host');

        return rtrim((string) $host, '/');
    }

    private function getSiteDescription(string $lang): string
    {
        return match ($lang) {
            'ru' => 'The Insider — расследования, аналитика, мнения',
            'en' => 'The Insider — investigations, analysis, opinions',
            default => 'The Insider',
        };
    }

    private function feedPath(
        string $lang,
        string $suffix,
        ?int $page = null,
        ?int $perPage = null
    ): string {
        $defaultLang = substr((string) config('app.locale'), 0, 2);
        $lang = $lang ?: $defaultLang;

        $path = $lang === $defaultLang
            ? '/feed' . $suffix
            : "/{$lang}/feed" . $suffix;

        $query = [];
        if ($page !== null && $page > 1) {
            $query['page'] = $page;
        }
        if ($perPage !== null && $perPage > 0) {
            $query['per_page'] = $perPage;
        }

        return $query ? $path . '?' . http_build_query($query) : $path;
    }
}
