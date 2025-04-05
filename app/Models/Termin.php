<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Termin extends Model {
    use HasFactory;

    protected $fillable = [
        'language_code',
        'termin',
        'description',
    ];

    public function posts() {
        return $this->belongsToMany(Post::class, 'post_termins');
    }
}
