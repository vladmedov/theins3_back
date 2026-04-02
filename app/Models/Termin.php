<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Termin extends Model {
    use HasFactory;

    protected $fillable = [
        'language_code',
        'termin',
        'description',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Termin $termin): void {
            if ($termin->isUsedInPosts()) {
                throw ValidationException::withMessages([
                    'termin' => [__('termin.cannot_delete_in_use')],
                ]);
            }
        });
    }

    public function posts() {
        return $this->belongsToMany(Post::class, 'post_termins');
    }

    public function isUsedInPosts(): bool
    {
        return $this->posts()->exists();
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
