<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

use App\Enums\PostTypes;
use App\Models\Post;
use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;

class Category extends Model {

    use HasFactory, SortableTrait;

    public const CONFESSION_BY_LANGUAGE = [
        'ru' => 11,
        'en' => 60,
    ];

    public static function getConfessionCategoryId(string $language_code): int
    {
        return self::CONFESSION_BY_LANGUAGE[$language_code] ?? self::CONFESSION_BY_LANGUAGE['ru'];
    }

    public $table = 'categories';

    public $sortable = [
      'order_column_name' => 'position',
      'sort_when_creating' => true,
    ];

    protected $fillable = [
        'id', // TODO: remove
        'language_code',
        'type',
        'slug',
        'title',
        'position',
        'is_show_in_menu',
        'widgets',
    ];

    protected $casts = [
        'type' => 'string', 
        'is_show_in_menu' => 'boolean',
        'widgets' => 'array',
    ];

    protected ?array $frontendRevalidationOriginalSnapshot = null;

    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($category) {
            if (!$category->exists || $category->frontendRevalidationOriginalSnapshot !== null) {
                return;
            }

            $category->frontendRevalidationOriginalSnapshot = app(FrontendCacheTagService::class)
                ->snapshotCategory(static::find($category->getKey()));
        });

        static::creating(function ($category) {
            if (!$category->position) {
                $nextPosition = (Category::max('position') ?? 0) + 1;
                $category->position = $nextPosition;
            }
        });

        static::saved(function ($category) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->unique(array_merge(
                    $tagService->tagsForCategorySnapshot($category->frontendRevalidationOriginalSnapshot),
                    $tagService->tagsForCategorySnapshot($tagService->snapshotCategory($category)),
                ))
            );

            $category->frontendRevalidationOriginalSnapshot = null;
        });

        static::deleted(function ($category) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->tagsForCategorySnapshot(
                    $category->frontendRevalidationOriginalSnapshot
                        ?? $tagService->snapshotCategory($category)
                )
            );

            $category->frontendRevalidationOriginalSnapshot = null;
        });
    }

    public static function getAvailableTypes() {
        return PostTypes::all();
    }

    public function posts() {
        return $this->hasMany(Post::class)
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderBy('published_at', 'desc');
    }
}
