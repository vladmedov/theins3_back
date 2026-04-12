<?php

namespace Medov\TopUsersCard;

use Laravel\Nova\Card;
use App\Models\Author;
use App\Models\Post;
use App\Enums\PostTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class TopUsersCard extends Card
{
    public const MODE_AUTHORS_POSTS = 'authors_posts';
    public const MODE_ARTICLE_VIEWS = 'article_views';
    private const CACHE_TTL_SECONDS = 900;

    public $width = '1/4';

    public function __construct($postType = PostTypes::NEWS, string $mode = self::MODE_AUTHORS_POSTS)
    {
        parent::__construct();

        $cacheKey = sprintf(
            'nova:top_users_card:%s:%s:%s:v2',
            app()->getLocale(),
            (string) $postType,
            $mode
        );
        $meta = Cache::remember(
            $cacheKey,
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn () => $this->buildCardMeta((string) $postType, $mode)
        );

        $this->withMeta([
            'periods' => $meta['periods'],
            'title' => $meta['title'],
            'defaultPeriod' => $meta['defaultPeriod'],
            'totalLabel' => $meta['totalLabel'],
            'secondaryLineSuffix' => $meta['secondaryLineSuffix'],
            'locale' => app()->getLocale(),
            'emptyLabel' => __('top_users_card.empty'),
            'isTallCard' => true,
        ]);
    }

    private function buildCardMeta(string $postType, string $mode): array
    {
        if ($mode === self::MODE_ARTICLE_VIEWS) {
            // Same windows as dashboard "news count" ranking (authors by posts).
            $weekFrom = now()->subDays(6)->startOfDay();
            $monthFrom = now()->subDays(29)->startOfDay();
            $yearFrom = now()->subDays(364)->startOfDay();

            return [
                'title' => __('top_users_card.titles.article_views'),
                'periods' => [
                    [
                        'key' => 'week',
                        'label' => __('top_users_card.periods.week'),
                        'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, $weekFrom),
                        'total' => $this->getArticleViewsTotalByPeriod($weekFrom),
                        'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, $weekFrom),
                    ],
                    [
                        'key' => 'month',
                        'label' => __('top_users_card.periods.month'),
                        'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, $monthFrom),
                        'total' => $this->getArticleViewsTotalByPeriod($monthFrom),
                        'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, $monthFrom),
                    ],
                    [
                        'key' => 'year',
                        'label' => __('top_users_card.periods.year'),
                        'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, $yearFrom),
                        'total' => $this->getArticleViewsTotalByPeriod($yearFrom),
                        'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, $yearFrom),
                    ],
                    [
                        'key' => 'always',
                        'label' => __('top_users_card.periods.always'),
                        'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, null),
                        'total' => $this->getArticleViewsTotalByPeriod(null),
                        'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, null),
                    ],
                ],
                'defaultPeriod' => 'week',
                'totalLabel' => __('top_users_card.totals.period_views'),
                'secondaryLineSuffix' => __('top_users_card.secondary.publications_suffix'),
            ];
        }

        // Keep ranking periods aligned with dashboard metrics buckets.
        $weekFrom = now()->subDays(6)->startOfDay();
        $monthFrom = now()->subDays(29)->startOfDay();
        $yearFrom = now()->subDays(364)->startOfDay();

        return [
            'title' => match ($postType) {
                PostTypes::ARTICLE => __('top_users_card.titles.article'),
                PostTypes::NEWS => __('top_users_card.titles.news'),
                PostTypes::OPINION => __('top_users_card.titles.opinion'),
                PostTypes::ONLINE => __('top_users_card.titles.online'),
                default => __('top_users_card.titles.default'),
            },
            'periods' => [
                [
                    'key' => 'week',
                    'label' => __('top_users_card.periods.week'),
                    'items' => $this->getTopAuthorsByPeriod($postType, $weekFrom),
                    'total' => $this->getViewsTotalByPeriod($postType, $weekFrom),
                    'secondary_total' => $this->getPostsTotalByPeriod($postType, $weekFrom),
                ],
                [
                    'key' => 'month',
                    'label' => __('top_users_card.periods.month'),
                    'items' => $this->getTopAuthorsByPeriod($postType, $monthFrom),
                    'total' => $this->getViewsTotalByPeriod($postType, $monthFrom),
                    'secondary_total' => $this->getPostsTotalByPeriod($postType, $monthFrom),
                ],
                [
                    'key' => 'year',
                    'label' => __('top_users_card.periods.year'),
                    'items' => $this->getTopAuthorsByPeriod($postType, $yearFrom),
                    'total' => $this->getViewsTotalByPeriod($postType, $yearFrom),
                    'secondary_total' => $this->getPostsTotalByPeriod($postType, $yearFrom),
                ],
                [
                    'key' => 'always',
                    'label' => __('top_users_card.periods.always'),
                    'items' => $this->getTopAuthorsByPeriod($postType, null),
                    'total' => $this->getViewsTotalByPeriod($postType, null),
                    'secondary_total' => $this->getPostsTotalByPeriod($postType, null),
                ],
            ],
            'defaultPeriod' => 'week',
            'totalLabel' => __('top_users_card.totals.period_views'),
            'secondaryLineSuffix' => __('top_users_card.secondary.publications_suffix'),
        ];
    }

    public static function articleViews(): self
    {
        return new self(PostTypes::ARTICLE, self::MODE_ARTICLE_VIEWS);
    }

    private function getTopAuthorsByPeriod(string $postType, ?\DateTimeInterface $from, int $limit = 10): array
    {
        $query = Author::query()
            ->select(['authors.id', 'authors.first_name', 'authors.last_name'])
            ->selectRaw('COUNT(DISTINCT posts.id) as posts_count')
            ->selectRaw('COALESCE(SUM(posts.views_count), 0) as views_sum')
            ->join('post_authors', 'post_authors.author_id', '=', 'authors.id')
            ->join('posts', 'posts.id', '=', 'post_authors.post_id')
            ->where('authors.language_code', app()->getLocale())
            ->where('posts.language_code', app()->getLocale())
            ->where('posts.type', $postType)
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->groupBy('authors.id', 'authors.first_name', 'authors.last_name')
            ->orderByDesc('posts_count')
            ->limit($limit);

        if ($from) {
            $query->whereBetween('posts.published_at', [$from, now()]);
        }

        return $query->get()
            ->map(fn (Author $author): array => [
                'id' => $author->id,
                'name' => trim($author->first_name . ' ' . ($author->last_name ?? '')),
                'posts_count' => (int) $author->posts_count,
                'views_count' => (int) $author->views_sum,
            ])
            ->all();
    }

    private function getTopAuthorsByViewsByPeriod(string $postType, ?\DateTimeInterface $from, int $limit = 10): array
    {
        $query = Author::query()
            ->select(['authors.id', 'authors.first_name', 'authors.last_name'])
            ->selectRaw('COUNT(DISTINCT posts.id) as posts_count')
            ->selectRaw('COALESCE(SUM(posts.views_count), 0) as views_sum')
            ->join('post_authors', 'post_authors.author_id', '=', 'authors.id')
            ->join('posts', 'posts.id', '=', 'post_authors.post_id')
            ->where('authors.language_code', app()->getLocale())
            ->where('posts.language_code', app()->getLocale())
            ->where('posts.type', $postType)
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->groupBy('authors.id', 'authors.first_name', 'authors.last_name')
            ->orderByDesc('views_sum')
            ->limit($limit);

        if ($from) {
            $query->whereBetween('posts.published_at', [$from, now()]);
        }

        return $query->get()
            ->map(fn (Author $author): array => [
                'id' => $author->id,
                'name' => trim($author->first_name . ' ' . ($author->last_name ?? '')),
                'posts_count' => (int) $author->posts_count,
                'views_count' => (int) $author->views_sum,
            ])
            ->all();
    }

    private function getPostsTotalByPeriod(string $postType, ?\DateTimeInterface $from): int
    {
        $query = $this->basePostsQuery($postType);

        if ($from) {
            $query->whereBetween('published_at', [$from, now()]);
        }

        return (int) $query->count();
    }

    private function getArticleViewsTotalByPeriod(?\DateTimeInterface $from): int
    {
        $query = $this->basePostsQuery(PostTypes::ARTICLE);

        if ($from) {
            $query->whereBetween('published_at', [$from, now()]);
        }

        return (int) ($query->sum('views_count') ?? 0);
    }

    private function getViewsTotalByPeriod(string $postType, ?\DateTimeInterface $from): int
    {
        $query = $this->basePostsQuery($postType);

        if ($from) {
            $query->whereBetween('published_at', [$from, now()]);
        }

        return (int) ($query->sum('views_count') ?? 0);
    }

    private function basePostsQuery(string $postType): Builder
    {
        return Post::query()
            ->where('language_code', app()->getLocale())
            ->where('type', $postType)
            ->where('status', Post::STATUS_PUBLISHED);
    }

    public function component(): string
    {
        return 'top-users-card';
    }
}
