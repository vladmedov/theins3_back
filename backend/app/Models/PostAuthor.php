<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
// use Spatie\EloquentSortable\Sortable;
// use Spatie\EloquentSortable\SortableTrait;

class PostAuthor extends Pivot //implements Sortable
{
    //use SortableTrait;

    protected $table = 'post_authors';

    public $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'position',
    ];

    // public $sortable = [
    //     'order_column_name' => 'position',
    //     'sort_when_creating' => true,
    // ];

    public function post() {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function author() {
        return $this->belongsTo(User::class, 'user_id');
    }

    // public function buildSortQuery()
    // {
    //     return static::query()->where('post_id', $this->post_id);
    // }
}
