<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

use App\Enums\PostTypes;

class Category extends Model {
    use HasFactory, SortableTrait;

    public $sortable = [
      'order_column_name' => 'position',
      'sort_when_creating' => true,
    ];

    protected $fillable = [
        'language_code',
        'type',
        'slug',
        'title',
        'position',
        'is_hidden',
        'widgets',
    ];

    protected $casts = [
        'type' => 'string', 
        'is_hidden' => 'boolean',
        'widgets' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            if (!$category->position) {
                $nextPosition = (Category::max('position') ?? 0) + 1;
                $category->position = $nextPosition;
            }
        });
    }

    public static function getAvailableTypes() {
        return PostTypes::all();
    }

    public function posts() {
        return $this->hasMany(Post::class);
    }
}
