<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Category;
use App\Models\InvestigationTheme;
use App\Models\Post;
use App\Models\Tag;

class FrontendCacheTagService
{
    public function snapshotPost(?Post $post): ?array
    {
        if (!$post) {
            return null;
        }

        $post->loadMissing([
            'category:id,slug',
            'tags:id,slug',
            'authors:id,slug',
            'columnist:id,slug',
            'investigationTheme:id,slug',
        ]);

        if (
            $post->status !== Post::STATUS_PUBLISHED
            || empty($post->language_code)
            || empty($post->slug)
            || empty($post->category?->slug)
        ) {
            return null;
        }

        return [
            'lang' => $post->language_code,
            'slug' => $post->slug,
            'category_slug' => $post->category->slug,
            'tag_slugs' => $post->tags->pluck('slug')->filter()->unique()->values()->all(),
            'author_slugs' => $post->authors->pluck('slug')->filter()->unique()->values()->all(),
            'columnist_slug' => $post->columnist?->slug,
            'investigation_slug' => $post->investigationTheme?->slug,
        ];
    }

    public function tagsForPostSnapshot(?array $snapshot): array
    {
        if (!$snapshot) {
            return [];
        }

        $lang = $snapshot['lang'];
        $categorySlug = $snapshot['category_slug'];
        $slug = $snapshot['slug'];

        $tags = [
            "home:{$lang}",
            "category:{$lang}:{$categorySlug}",
            "post:{$lang}:{$categorySlug}:{$slug}",
        ];

        foreach ($snapshot['tag_slugs'] ?? [] as $tagSlug) {
            $tags[] = "tag:{$lang}:{$tagSlug}";
        }

        foreach ($snapshot['author_slugs'] ?? [] as $authorSlug) {
            $tags[] = "author:{$lang}:{$authorSlug}";
        }

        if (!empty($snapshot['columnist_slug'])) {
            $tags[] = "columnist:{$lang}:{$snapshot['columnist_slug']}";
        }

        if (!empty($snapshot['investigation_slug'])) {
            $tags[] = "investigations:{$lang}";
            $tags[] = "investigation:{$lang}:{$snapshot['investigation_slug']}";
        }

        return $this->unique($tags);
    }

    public function snapshotCategory(?Category $category): ?array
    {
        if (!$category || empty($category->language_code) || empty($category->slug)) {
            return null;
        }

        return [
            'lang' => $category->language_code,
            'slug' => $category->slug,
        ];
    }

    public function tagsForCategorySnapshot(?array $snapshot): array
    {
        if (!$snapshot) {
            return [];
        }

        $lang = $snapshot['lang'];
        $slug = $snapshot['slug'];

        return $this->unique([
            "layout:{$lang}",
            "home:{$lang}",
            "category:{$lang}:{$slug}",
        ]);
    }

    public function snapshotTag(?Tag $tag): ?array
    {
        if (!$tag || empty($tag->language_code) || empty($tag->slug)) {
            return null;
        }

        return [
            'lang' => $tag->language_code,
            'slug' => $tag->slug,
        ];
    }

    public function tagsForTagSnapshot(?array $snapshot): array
    {
        if (!$snapshot) {
            return [];
        }

        $lang = $snapshot['lang'];
        $slug = $snapshot['slug'];

        return $this->unique([
            "home:{$lang}",
            "tag:{$lang}:{$slug}",
        ]);
    }

    public function snapshotAuthor(?Author $author): ?array
    {
        if (!$author || empty($author->language_code) || empty($author->slug)) {
            return null;
        }

        return [
            'lang' => $author->language_code,
            'slug' => $author->slug,
        ];
    }

    public function tagsForAuthorSnapshot(?array $snapshot): array
    {
        if (!$snapshot) {
            return [];
        }

        $lang = $snapshot['lang'];
        $slug = $snapshot['slug'];

        return $this->unique([
            "home:{$lang}",
            "author:{$lang}:{$slug}",
            "columnist:{$lang}:{$slug}",
        ]);
    }

    public function snapshotInvestigationTheme(?InvestigationTheme $theme): ?array
    {
        if (!$theme || empty($theme->language_code) || empty($theme->slug)) {
            return null;
        }

        return [
            'lang' => $theme->language_code,
            'slug' => $theme->slug,
        ];
    }

    public function tagsForInvestigationThemeSnapshot(?array $snapshot): array
    {
        if (!$snapshot) {
            return [];
        }

        $lang = $snapshot['lang'];
        $slug = $snapshot['slug'];

        return $this->unique([
            "home:{$lang}",
            "investigations:{$lang}",
            "investigation:{$lang}:{$slug}",
        ]);
    }

    public function unique(array $tags): array
    {
        return array_values(array_unique(array_filter($tags)));
    }
}
