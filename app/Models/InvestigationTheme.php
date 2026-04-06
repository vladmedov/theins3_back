<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;
use App\Services\ImageService;

class InvestigationTheme extends Model {
    use HasFactory, SortableTrait;

    public $table = 'investigation_themes';

    public $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
      ];

    protected $fillable = [
        'id',   // TODO: remove
        'language_code',
        'slug',
        'title',
        'description',
        'position',
        'cover_image',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    protected ?array $frontendRevalidationOriginalSnapshot = null;

    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($investigationTheme) {
            if (!$investigationTheme->exists || $investigationTheme->frontendRevalidationOriginalSnapshot !== null) {
                return;
            }

            $investigationTheme->frontendRevalidationOriginalSnapshot = app(FrontendCacheTagService::class)
                ->snapshotInvestigationTheme(static::find($investigationTheme->getKey()));
        });

        static::creating(function ($investigationTheme) {
            if (!$investigationTheme->position) {
                $prevPosition = (InvestigationTheme::min('position') ?? 0) - 1;
                $investigationTheme->position = $prevPosition;
            }
        });

        static::saving(function ($investigationTheme) {
            if ($investigationTheme->is_main) {
                static
                    ::where('language_code', $investigationTheme->language_code)
                    ->where('id', '!=', $investigationTheme->id)
                    ->update(['is_main' => false]);
            }
        });

        static::saved(function ($theme) {
            $shouldProcessCover = !empty($theme->cover_image)
                && ($theme->wasRecentlyCreated || $theme->wasChanged('cover_image'));

            if ($shouldProcessCover) {
                $path = ImageService::relocateOriginalIfNeeded(
                    $theme->id,
                    $theme->cover_image,
                    ImageService::TYPE_THEME_COVER,
                    $theme->language_code
                );
                if (!empty($path)) {
                    if ($path !== $theme->cover_image) {
                        $theme->cover_image = $path;
                        $theme->saveQuietly();
                    }
                    ImageService::createImageVariants($theme->id, $path, ImageService::TYPE_THEME_COVER, $theme->language_code);
                }
            }

            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->unique(array_merge(
                    $tagService->tagsForInvestigationThemeSnapshot($theme->frontendRevalidationOriginalSnapshot),
                    $tagService->tagsForInvestigationThemeSnapshot($tagService->snapshotInvestigationTheme($theme)),
                ))
            );

            $theme->frontendRevalidationOriginalSnapshot = null;
        });

        static::deleted(function ($theme) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->tagsForInvestigationThemeSnapshot(
                    $theme->frontendRevalidationOriginalSnapshot
                        ?? $tagService->snapshotInvestigationTheme($theme)
                )
            );

            $theme->frontendRevalidationOriginalSnapshot = null;
        });
    }
    

    /**
     * Публикации, относящиеся к теме расследования.
     */
    public function posts() {
        return $this->hasMany(Post::class)
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderBy('published_at', 'desc');
    }

    public function getCoverImageUrlAttribute()
    {
        if (empty($this->cover_image)) {
            return null;
        }

        if (str_starts_with($this->cover_image, 'theme/')) {
            return ImageService::getImageUrl($this->id, $this->cover_image, ImageService::TYPE_THEME_COVER, ImageService::SIZE_ORIGINAL, false);
        }

        return null;
    }

    public function getPath() {
        return '/'
            . ($this->language_code === 'ru' ? 'investigations/' : "{$this->language_code}/investigations/")
            . "{$this->slug}";
    }
}
