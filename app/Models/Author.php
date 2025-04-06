<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

use App\Enums\PostTypes;

class Author extends Model {
    use HasFactory;

    public $table = 'authors';

    protected $fillable = [
        'id', // TODO: check
        'language_code',
        'slug',
        'first_name',
        'last_name',
        'avatar',
        'position',
        'description',
        'twitter',
        'facebook',
        'allowed_post_types',
        'post_types_with_hidden_author_name',
        'is_author_page_hidden',
        'is_columnist_page_hidden',
    ];

    protected $casts = [
        'allowed_post_types' => 'array',
        'post_types_with_hidden_author_name' => 'array',
        'is_author_page_hidden' => 'boolean',
        'is_columnist_page_hidden' => 'boolean',
    ];

    public function getFullNameAttribute() {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAvatarUrlAttribute() {
        return $this->avatar ? 'https://insidertexts.com/storage/person/' . $this->id . '/' . $this->avatar : null; // TODO: remove
        //return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function posts() {
        return $this
            ->belongsToMany(Post::class, 'post_authors', 'author_id', 'post_id')
            ->where('language_code', $this->language_code);
    }

    public function news() {
        return $this
            ->posts()
            ->where('type', PostTypes::NEWS)
            ->limit(36);
    }

    public function articles() {
        return $this
            ->posts()
            ->whereIn('type', [PostTypes::ARTICLE, PostTypes::ONLINE])
            ->limit(36);
    }

    public function opinions() {
        return $this
            ->hasMany(Post::class, 'columnist_id', 'id')
            ->where('language_code', $this->language_code)
            ->where('type', PostTypes::OPINION)
            ->limit(36);
    }

    public function notOpinions() {
        return $this
            ->posts()
            ->whereNot('type', PostTypes::OPINION)
            ->limit(36);
    }

    public static function getAuthorsByPostType($languageCode, $postTypes = [PostTypes::ARTICLE, PostTypes::ONLINE, PostTypes::NEWS, PostTypes::OPINION]) {
        return self::where('language_code', $languageCode)
            ->whereJsonContains('allowed_post_types', $postTypes)
            ->get()
            ->pluck('full_name', 'id');
    }
}
