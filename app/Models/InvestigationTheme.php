<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

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
    }
    

    /**
     * Публикации, относящиеся к теме расследования.
     */
    public function posts() {
        return $this->hasMany(Post::class)->orderBy('published_at', 'desc');
    }
}
