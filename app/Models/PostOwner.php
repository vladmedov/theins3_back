<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostOwner extends Model {
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
    ];

    /**
     * Связь с публикацией.
     */
    public function post() {
        return $this->belongsTo(Post::class);
    }

    /**
     * Связь с пользователем (владелец публикации).
     */
    public function user() {
        return $this->belongsTo(User::class);
    }
}
