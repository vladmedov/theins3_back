<?php

namespace Medov\TopUsersCard;

use Laravel\Nova\Card;
use App\Models\Author;
use App\Models\Post;
use App\Enums\PostTypes;
use Illuminate\Database\Eloquent\Builder;

class TopUsersCard extends Card
{
    public const MODE_AUTHORS_POSTS = 'authors_posts';
    public const MODE_ARTICLE_VIEWS = 'article_views';

    public $width = '1/4';

    public function __construct($postType = PostTypes::NEWS, string $mode = self::MODE_AUTHORS_POSTS)
    {
        parent::__construct();

        if ($mode === self::MODE_ARTICLE_VIEWS) {
            $title = __('top_users_card.titles.article_views');
            $articleMonthFrom = now()->subDays(29)->startOfDay();
            $periods = [
                [
                    'key' => 'month',
                    'label' => __('top_users_card.periods.month'),
                    'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, $articleMonthFrom),
                    'total' => $this->getArticleViewsTotalByPeriod($articleMonthFrom),
                    'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, $articleMonthFrom),
                ],
                [
                    'key' => 'quarter',
                    'label' => __('top_users_card.periods.quarter'),
                    'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, now()->subMonths(3)),
                    'total' => $this->getArticleViewsTotalByPeriod(now()->subMonths(3)),
                    'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, now()->subMonths(3)),
                ],
                [
                    'key' => 'year',
                    'label' => __('top_users_card.periods.year'),
                    'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, now()->subYear()),
                    'total' => $this->getArticleViewsTotalByPeriod(now()->subYear()),
                    'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, now()->subYear()),
                ],
                [
                    'key' => 'always',
                    'label' => __('top_users_card.periods.always'),
                    'items' => $this->getTopAuthorsByViewsByPeriod(PostTypes::ARTICLE, null),
                    'total' => $this->getArticleViewsTotalByPeriod(null),
                    'secondary_total' => $this->getPostsTotalByPeriod(PostTypes::ARTICLE, null),
                ],
            ];
            $defaultPeriod = 'month';
            $totalLabel = __('top_users_card.totals.article_views');
            $secondaryLinePrefix = __('top_users_card.secondary.on_posts_prefix');
            $secondaryLineSuffix = __('top_users_card.secondary.on_posts_suffix');
        } else {
            // Keep ranking periods aligned with dashboard metrics buckets.
            $weekFrom = now()->subDays(6)->startOfDay();
            $monthFrom = now()->subDays(29)->startOfDay();
            $yearFrom = now()->subDays(364)->startOfDay();

            $title = match ($postType) {
                PostTypes::ARTICLE => __('top_users_card.titles.article'),
                PostTypes::NEWS => __('top_users_card.titles.news'),
                PostTypes::OPINION => __('top_users_card.titles.opinion'),
                PostTypes::ONLINE => __('top_users_card.titles.online'),
                default => __('top_users_card.titles.default'),
            };

            $periods = [
                [
                    'key' => 'week',
                    'label' => __('top_users_card.periods.week'),
                    'items' => $this->getTopAuthorsByPeriod($postType, $weekFrom),
                    'total' => $this->getPostsTotalByPeriod($postType, $weekFrom),
                    'secondary_total' => $this->getViewsTotalByPeriod($postType, $weekFrom),
                ],
                [
                    'key' => 'month',
                    'label' => __('top_users_card.periods.month'),
                    'items' => $this->getTopAuthorsByPeriod($postType, $monthFrom),
                    'total' => $this->getPostsTotalByPeriod($postType, $monthFrom),
                    'secondary_total' => $this->getViewsTotalByPeriod($postType, $monthFrom),
                ],
                [
                    'key' => 'year',
                    'label' => __('top_users_card.periods.year'),
                    'items' => $this->getTopAuthorsByPeriod($postType, $yearFrom),
                    'total' => $this->getPostsTotalByPeriod($postType, $yearFrom),
                    'secondary_total' => $this->getViewsTotalByPeriod($postType, $yearFrom),
                ],
                [
                    'key' => 'always',
                    'label' => __('top_users_card.periods.always'),
                    'items' => $this->getTopAuthorsByPeriod($postType, null),
                    'total' => $this->getPostsTotalByPeriod($postType, null),
                    'secondary_total' => $this->getViewsTotalByPeriod($postType, null),
                ],
            ];
            $defaultPeriod = 'week';
            $totalLabel = __('top_users_card.totals.news_total');
            $secondaryLinePrefix = __('top_users_card.secondary.brought_views_prefix');
            $secondaryLineSuffix = __('top_users_card.secondary.brought_views_suffix');
        }

        $this->withMeta([
            'periods' => $periods,
            'title' => $title,
            'defaultPeriod' => $defaultPeriod,
            'totalLabel' => $totalLabel,
            'secondaryLinePrefix' => $secondaryLinePrefix,
            'secondaryLineSuffix' => $secondaryLineSuffix,
            'locale' => app()->getLocale(),
            'emptyLabel' => __('top_users_card.empty'),
            'isTallCard' => true,
        ]);
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
                'value' => (int) $author->posts_count,
            ])
            ->all();
    }

    private function getTopAuthorsByViewsByPeriod(string $postType, ?\DateTimeInterface $from, int $limit = 10): array
    {
        $query = Author::query()
            ->select(['authors.id', 'authors.first_name', 'authors.last_name'])
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
                'value' => (int) $author->views_sum,
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
