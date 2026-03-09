<?php

namespace Tests\Unit;

use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;
use Tests\TestCase;

class FrontendCacheTagServiceTest extends TestCase
{
    public function test_it_builds_post_tags_from_snapshot(): void
    {
        $service = new FrontendCacheTagService();

        $tags = $service->tagsForPostSnapshot([
            'lang' => 'ru',
            'slug' => 'some-slug',
            'category_slug' => 'news',
            'tag_slugs' => ['vs', 'important'],
            'author_slugs' => ['ivanov'],
            'columnist_slug' => 'petrov',
            'investigation_slug' => 'theme-1',
        ]);

        $this->assertSame([
            'home:ru',
            'category:ru:news',
            'post:ru:news:some-slug',
            'tag:ru:vs',
            'tag:ru:important',
            'author:ru:ivanov',
            'columnist:ru:petrov',
            'investigations:ru',
            'investigation:ru:theme-1',
        ], $tags);
    }

    public function test_it_builds_category_tags_from_snapshot(): void
    {
        $service = new FrontendCacheTagService();

        $this->assertSame([
            'layout:en',
            'home:en',
            'category:en:news',
        ], $service->tagsForCategorySnapshot([
            'lang' => 'en',
            'slug' => 'news',
        ]));
    }

    public function test_it_builds_author_tags_from_snapshot(): void
    {
        $service = new FrontendCacheTagService();

        $this->assertSame([
            'home:ru',
            'author:ru:john-doe',
            'columnist:ru:john-doe',
        ], $service->tagsForAuthorSnapshot([
            'lang' => 'ru',
            'slug' => 'john-doe',
        ]));
    }

    public function test_it_adds_layout_tag_when_post_category_changes(): void
    {
        $service = new class extends FrontendRevalidationService {
            public function exposeTagsForCategoryChange(?array $beforeSnapshot, ?array $currentSnapshot): array
            {
                return $this->tagsForCategoryChange($beforeSnapshot, $currentSnapshot);
            }
        };

        $this->assertSame([
            'layout:ru',
        ], $service->exposeTagsForCategoryChange(
            [
                'lang' => 'ru',
                'category_slug' => 'news',
            ],
            [
                'lang' => 'ru',
                'category_slug' => 'articles',
            ],
        ));
    }
}
