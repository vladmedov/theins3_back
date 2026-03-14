<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;

class CollectionPost extends Pivot implements Sortable {
    use SortableTrait;

    protected $table = 'collection_post';

    const COLLECTION_CODE_MAIN_OPINIONS = 'main_opinions';
    const COLLECTION_CODE_FEATURE = 'feature';
    const COLLECTION_CODE_POPULAR = 'popular';
    const COLLECTION_CODE_TOP_NEWS = 'top_news';

    protected $fillable = [
        'language_code',
        'collection_code',
        'post_id',
        'position',
        'created_at',
        'updated_at',
    ];

    protected $uniqueKeys = ['language_code', 'collection_code', 'post_id'];
    public $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    public $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => false,  // Отключено - position устанавливается вручную
    ];

    protected static function booted(): void
    {
        static::creating(function (self $collectionPost) {
            if (!empty($collectionPost->position)) {
                return;
            }

            $collectionPost->position = static::query()
                ->where('language_code', $collectionPost->language_code)
                ->where('collection_code', $collectionPost->collection_code)
                ->max('position') + 1;
        });

        static::saved(function (self $collectionPost) {
            if (!$collectionPost->shouldRevalidateAfterSave()) {
                return;
            }

            $collectionPost->queueFrontendRevalidation();
        });

        static::deleted(function (self $collectionPost) {
            $collectionPost->queueFrontendRevalidation();
        });
    }

    public function buildSortQuery() {
        return static::query()
            ->where('language_code', $this->language_code)
            ->where('collection_code', $this->collection_code);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    protected function queueFrontendRevalidation(): void
    {
        $tags = [
            'home:' . $this->language_code,
            'layout:' . $this->language_code,
        ];

        $post = $this->post()
            ->with([
                'category:id,slug',
                'tags:id,slug',
                'authors:id,slug',
                'columnist:id,slug',
                'investigationTheme:id,slug',
            ])
            ->first();

        if ($post) {
            $tagService = app(FrontendCacheTagService::class);
            $tags = array_merge(
                $tags,
                $tagService->tagsForPostSnapshot($tagService->snapshotPost($post))
            );
        }

        app(FrontendRevalidationService::class)->queueTags(
            app(FrontendCacheTagService::class)->unique($tags)
        );
    }

    protected function shouldRevalidateAfterSave(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged([
            'position',
            'post_id',
            'collection_code',
            'language_code',
        ]);
    }
}
