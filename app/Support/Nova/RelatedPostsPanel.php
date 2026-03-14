<?php

namespace App\Support\Nova;

use App\Enums\PostTypes;
use Illuminate\Pagination\LengthAwarePaginator;

class RelatedPostsPanel
{
    public static function render(
        ?LengthAwarePaginator $relatedPostsPaginator,
        ?callable $buildPageUrl = null,
        array $options = []
    ): string
    {
        if (!$relatedPostsPaginator || $relatedPostsPaginator->total() === 0) {
            return '';
        }

        $posts = collect($relatedPostsPaginator->items())->map(function ($post) {
            return [
                'id' => $post->id,
                'title' => trim((string) $post->title) !== '' ? $post->title : ('#' . $post->id),
                'type_label' => self::getPostTypeLabel($post->type),
                'published_at' => self::formatPublishedAt($post->published_at),
                'edit_url' => self::buildPostEditUrl($post),
            ];
        });

        return view('nova.components.related-posts-panel', [
            'posts' => $posts,
            'total' => $relatedPostsPaginator->total(),
            'currentPage' => $relatedPostsPaginator->currentPage(),
            'lastPage' => $relatedPostsPaginator->lastPage(),
            'previousUrl' => $relatedPostsPaginator->onFirstPage() || !$buildPageUrl
                ? null
                : $buildPageUrl($relatedPostsPaginator->currentPage() - 1),
            'nextUrl' => $relatedPostsPaginator->hasMorePages() && $buildPageUrl
                ? $buildPageUrl($relatedPostsPaginator->currentPage() + 1)
                : null,
            'showHeader' => $options['showHeader'] ?? true,
            'withOuterCard' => $options['withOuterCard'] ?? true,
        ])->render();
    }

    protected static function getPostTypeLabel(string $postType): string
    {
        return match ($postType) {
            PostTypes::NEWS => __('related_posts_panel.post_types.news'),
            PostTypes::ARTICLE => __('related_posts_panel.post_types.article'),
            PostTypes::OPINION => __('related_posts_panel.post_types.opinion'),
            PostTypes::ONLINE => __('related_posts_panel.post_types.online'),
            default => $postType,
        };
    }

    protected static function buildPostEditUrl(object $post): ?string
    {
        $resourceClass = match ($post->type) {
            PostTypes::NEWS => \App\Nova\_Posts\PostNews::class,
            PostTypes::ARTICLE => \App\Nova\_Posts\PostArticle::class,
            PostTypes::OPINION => \App\Nova\_Posts\PostOpinion::class,
            PostTypes::ONLINE => \App\Nova\_Posts\PostOnline::class,
            default => null,
        };

        if (!$resourceClass) {
            return null;
        }

        return '/admin/resources/' . $resourceClass::uriKey() . '/' . $post->id . '/edit';
    }

    protected static function formatPublishedAt($publishedAt): ?string
    {
        if (!$publishedAt) {
            return null;
        }

        $userTz = auth()->user()->timezone ?? config('app.timezone');

        if ($userTz && in_array($userTz, timezone_identifiers_list(), true)) {
            $publishedAt = $publishedAt->copy()->setTimezone($userTz);
        }

        return $publishedAt->format('d.m.Y H:i');
    }
}
