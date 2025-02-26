<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class CollectionPost extends Pivot implements Sortable {
    use SortableTrait;

    protected $table = 'collection_post';

    protected $fillable = [
        'language_code',
        'collection_code',
        'post_id',
        'position'
    ];

    protected $uniqueKeys = ['language_code', 'collection_code', 'post_id'];
    public $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    public $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    public function buildSortQuery() {
        return static::query()
            ->where('language_code', $this->collection_code)
            ->where('collection_code', $this->collection_code);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
