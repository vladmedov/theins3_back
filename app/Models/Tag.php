<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;

class Tag extends Model {
    use HasFactory;

    public $table = 'tags';

    protected $fillable = [
        'language_code',
        'slug',
        'title',
    ];

    protected ?array $frontendRevalidationOriginalSnapshot = null;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($tag) {
            if (!$tag->exists || $tag->frontendRevalidationOriginalSnapshot !== null) {
                return;
            }

            $tag->frontendRevalidationOriginalSnapshot = app(FrontendCacheTagService::class)
                ->snapshotTag(static::find($tag->getKey()));
        });

        static::saved(function ($tag) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->unique(array_merge(
                    $tagService->tagsForTagSnapshot($tag->frontendRevalidationOriginalSnapshot),
                    $tagService->tagsForTagSnapshot($tagService->snapshotTag($tag)),
                ))
            );

            $tag->frontendRevalidationOriginalSnapshot = null;
        });

        static::deleted(function ($tag) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->tagsForTagSnapshot(
                    $tag->frontendRevalidationOriginalSnapshot
                        ?? $tagService->snapshotTag($tag)
                )
            );

            $tag->frontendRevalidationOriginalSnapshot = null;
        });
    }

    public function posts() {
        return $this->belongsToMany(Post::class, 'post_tags')
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderBy('published_at', 'desc');
    }
}
