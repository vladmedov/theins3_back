<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

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

    protected static function boot()
    {
        parent::boot();
        
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

        static::updated(function ($theme) {
            if ($theme->wasChanged('cover_image') && !empty($theme->cover_image)) {
                ImageService::createImageVariants($theme->id, $theme->cover_image, ImageService::TYPE_THEME_COVER);
            }
        });

        static::created(function ($theme) {
            if (!empty($theme->cover_image)) {
                ImageService::createImageVariants($theme->id, $theme->cover_image, ImageService::TYPE_THEME_COVER);
            }
        });
    }
    

    /**
     * Публикации, относящиеся к теме расследования.
     */
    public function posts() {
        return $this->hasMany(Post::class)->orderBy('published_at', 'desc');
    }

    public function getCoverImageUrlAttribute()
    {
        if (empty($this->cover_image)) {
            return null;
        }

        if (str_starts_with($this->cover_image, 'theme/')) {
            return ImageService::getImageUrl($this->id, $this->cover_image, ImageService::TYPE_THEME_COVER, ImageService::SIZE_ORIGINAL, true);
        }

        return null;
    }

    public function getPath() {
        return '/'
            . ($this->language_code === 'ru' ? 'investigations/' : "{$this->language_code}/investigations/")
            . "{$this->slug}";
    }
}
