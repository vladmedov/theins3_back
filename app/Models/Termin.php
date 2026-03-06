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

    /**
     * Return a globally unique termin name (the DB unique index covers the
     * `termin` column across all languages).
     * If $base is already taken, appends " (2)", " (3)", … without limit.
     */
    public static function uniqueName(string $base): string
    {
        if (!static::where('termin', $base)->exists()) {
            return $base;
        }

        $n = 2;
        do {
            $candidate = "{$base} ({$n})";
            $n++;
        } while (static::where('termin', $candidate)->exists());

        return $candidate;
    }
}
