<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model {
    use HasFactory;

    public $table = 'tags';

    protected $fillable = [
        'language_code',
        'slug',
        'title',
    ];

    public function posts() {
        return $this->belongsToMany(Post::class, 'post_tags')
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderBy('published_at', 'desc');
    }
}
