<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostTag extends Model {
    use HasFactory;

    protected $fillable = [
        'post_id',
        'tag_id',
    ];

    /**
     * Связь с публикацией.
     */
    public function post() {
        return $this->belongsTo(Post::class);
    }

    /**
     * Связь с тегом.
     */
    public function tag() {
        return $this->belongsTo(Tag::class);
    }
}
