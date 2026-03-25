<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

use App\Enums\PostTypes;
use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;
use App\Services\ImageService;

class Author extends Model {
    use HasFactory;

    public $table = 'authors';

    protected $fillable = [
        'id', // TODO: check
        'language_code',
        'user_id',
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

    protected ?array $frontendRevalidationOriginalSnapshot = null;

    public static function boot()
    {
        parent::boot();

        static::saving(function ($author) {
            if (!$author->exists || $author->frontendRevalidationOriginalSnapshot !== null) {
                return;
            }

            $author->frontendRevalidationOriginalSnapshot = app(FrontendCacheTagService::class)
                ->snapshotAuthor(static::find($author->getKey()));
        });

        static::updated(function ($author) {
            if ($author->wasChanged('avatar') && !empty($author->avatar)) {
                ImageService::createImageVariants($author->id, $author->avatar, ImageService::TYPE_USER_PHOTO);
            }
        });

        static::created(function ($author) {
            if (!empty($author->avatar)) {
                ImageService::createImageVariants($author->id, $author->avatar, ImageService::TYPE_USER_PHOTO);
            }
        });

        static::saved(function ($author) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->unique(array_merge(
                    $tagService->tagsForAuthorSnapshot($author->frontendRevalidationOriginalSnapshot),
                    $tagService->tagsForAuthorSnapshot($tagService->snapshotAuthor($author)),
                ))
            );

            $author->frontendRevalidationOriginalSnapshot = null;
        });

        static::deleted(function ($author) {
            $tagService = app(FrontendCacheTagService::class);

            app(FrontendRevalidationService::class)->queueTags(
                $tagService->tagsForAuthorSnapshot(
                    $author->frontendRevalidationOriginalSnapshot
                        ?? $tagService->snapshotAuthor($author)
                )
            );

            $author->frontendRevalidationOriginalSnapshot = null;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute() {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute()
    {
        if (empty($this->avatar)) {
            return null;
        }
        // Новый формат: avatar = user_photo/original/hash1/hash2/filename
        if (str_starts_with($this->avatar, 'user_photo/')) {
            return ImageService::getImageUrl($this->id, $this->avatar, ImageService::TYPE_USER_PHOTO, ImageService::SIZE_ORIGINAL, false);
        } else {
            return null;
        }
    }

    public function posts() {
        return $this
            ->belongsToMany(Post::class, 'post_authors', 'author_id', 'post_id')
            ->where('language_code', $this->language_code)
            ->where('status', Post::STATUS_PUBLISHED);
    }

    public function news() {
        return $this
            ->posts()
            ->where('type', PostTypes::NEWS)
            ->orderBy('published_at', 'desc')
            ->limit(36);
    }

    public function articles() {
        return $this
            ->posts()
            ->whereIn('type', [PostTypes::ARTICLE, PostTypes::ONLINE])
            ->orderBy('published_at', 'desc')
            ->limit(36);
    }

    public function opinions() {
        return $this
            ->hasMany(Post::class, 'columnist_id', 'id')
            ->where('language_code', $this->language_code)
            ->where('type', PostTypes::OPINION)
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderBy('published_at', 'desc')
            ->limit(36);
    }

    public function notOpinions() {
        return $this
            ->posts()
            ->whereNot('type', PostTypes::OPINION)
            ->where('status', Post::STATUS_PUBLISHED)
            ->orderBy('published_at', 'desc')
            ->limit(36);
    }

    public static function getAuthorsByPostType($languageCode, $postTypes = [PostTypes::ARTICLE, PostTypes::ONLINE, PostTypes::NEWS, PostTypes::OPINION]) {
        return self::where('language_code', $languageCode)
            ->whereJsonContains('allowed_post_types', $postTypes)
            ->get()
            ->pluck('full_name', 'id');
    }
}
