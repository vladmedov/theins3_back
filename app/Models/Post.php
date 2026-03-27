<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use Whitecube\NovaFlexibleContent\Value\FlexibleCast;
use App\Casts\CompactFlexibleCast;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;
use \App\Models\PostTypes\OnlineMessage;

use App\Services\ChangeDetectorService;
use App\Services\FrontendCacheTagService;
use App\Services\FrontendRevalidationService;
use App\Services\ImageService;
use App\Services\SearchIndexManager;
use App\Services\ShareImageService;

use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

// use Spatie\MediaLibrary\HasMedia;
// use Spatie\MediaLibrary\InteractsWithMedia;
// use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model { //implements HasMedia {

    use Searchable;
    //use InteractsWithMedia;

    const STATUS_PUBLISHED = 'published';
    const STATUS_DRAFT = 'draft';

    public $table = 'posts';

    public static $title = 'title';

    public static $search = ['title', 'slug', 'author_name']; 

    public $with = ['category', 'authors', 'columnist'];

    protected $fillable = [
        'id', // TODO: remove
        'language_code',
        'category_id',
        'translation_id',
        'columnist_id',
        'author_visibility',
        'investigation_theme_id',
        'published_at',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'type',
        'status',
        'slug',
        'title',
        'lead',
        'content',
        'image',
        'image_description',
        'title_feature',
        'is_super_news',
        'views_count',
        'auto_publish_pending',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'created_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'updated_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'published_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'views_count' => 'integer',
        'content' => CompactFlexibleCast::class,
        'author_visibility' => 'string',
        'auto_publish_pending' => 'boolean',
    ];

    protected $attributes = [
        'views_count' => 0,
    ];

    protected ?array $frontendRevalidationOriginalSnapshot = null;

    public static function boot() {
        parent::boot();

        static::saving(function (Post $post) {
            $publishClickAction = app()->bound('request')
                ? request()->headers->get('X-Nova-Post-Publish-Click')
                : null;

            if ($publishClickAction === 'publish') {
                $post->status = self::STATUS_PUBLISHED;
                $post->published_at = now();
            } elseif ($publishClickAction === 'unpublish') {
                $post->status = self::STATUS_DRAFT;
            }

            if ($post->status === self::STATUS_PUBLISHED && empty($post->image)) {
                throw ValidationException::withMessages([
                    'image' => __('posts.image_required_for_published'),
                ]);
            }

            if ($post->status === self::STATUS_PUBLISHED || !$post->published_at) {
                $post->auto_publish_pending = false;

                return;
            }
            if ($post->published_at->lte(now())) {
                $post->auto_publish_pending = false;
            }
        });

        // Объединяем логику создания PostHistory в отдельный метод
        $createHistory = function($post, $oldData, $newData, $status) {
            if (!auth()->check()) {
                return;
            }
            
            $oldData['content'] = isset($oldData['content']) ? CompactFlexibleCast::convertToDbFormat($oldData['content']) : null;
            $changes = ChangeDetectorService::compare($oldData, $newData, ['created_at', 'updated_at']);
    
            if (!empty($changes)) {
                PostHistory::create([
                    'post_id' => $post->id,
                    'user_id' => auth()->user()->id,
                    'status' => $status,
                    'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE)
                ]);
            }
        };

        static::created(function ($post) use ($createHistory) {
            $createHistory($post, [], $post->getAttributes(), 'created');
        });

        static::saving(function ($post) {
            if (!$post->exists || $post->frontendRevalidationOriginalSnapshot !== null) {
                return;
            }

            $originalPost = static::query()
                ->with([
                    'category:id,slug',
                    'tags:id,slug',
                    'authors:id,slug',
                    'columnist:id,slug',
                    'investigationTheme:id,slug',
                ])
                ->find($post->getKey());

            $post->frontendRevalidationOriginalSnapshot = app(FrontendCacheTagService::class)
                ->snapshotPost($originalPost);
        });

        static::updating(function ($post) use ($createHistory) {
            $createHistory($post, $post->getOriginal(), $post->getAttributes(), 'updated');
        });

        static::deleting(function ($post) use ($createHistory) {
            $createHistory($post, $post->getOriginal(), [], 'deleted');
        });

        static::saved(function ($post) {
            $updated = false;
        
            if (empty($post->slug)) {
                $post->slug = (string)$post->id;
                $updated = true;
            }
        
            if (empty($post->seo_title) && !empty($post->title)) {
                $post->seo_title = $post->title;
                $updated = true;
            }

            if (!empty($post->translation_id)) {
                $post->translation()->update([
                    'translation_id' => $post->id,
                ]);
            } else {
                $post->translation()->update([
                    'translation_id' => null,
                ]);
            }
        
            if ($updated) {
                $post->saveQuietly();
            }

            $shouldGenerateImages = !empty($post->image)
                && ($post->wasRecentlyCreated || $post->wasChanged('image'));

            if ($shouldGenerateImages) {
                $post->createImageVariants();
            }

            // Keep automatic writes and full reindex on the same alias-based scheme.
            $searchIndexManager = app(SearchIndexManager::class);
            $searchIndexName = $post->searchableAs();

            if ($post->shouldBeSearchable()) {
                $searchIndexManager->ensureWriteTarget($post);
                $post->searchable();
            } elseif (
                $searchIndexManager->aliasExists($searchIndexName)
                || $searchIndexManager->indexExists($searchIndexName)
            ) {
                $post->unsearchable();
            }

            // Sync post_termins pivot based on <span data-id="X"> tags in content.
            $raw = \Illuminate\Support\Facades\DB::table('posts')->where('id', $post->id)->value('content');
            if (!empty($raw)) {
                $blocks = json_decode($raw, true) ?? [];
                $terminIds = [];
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $html = $block['attributes']['text'] ?? '';
                        preg_match_all('/data-id="(\d+)"/', $html, $matches);
                        $terminIds = array_merge($terminIds, $matches[1]);
                    }
                }
                $post->termins()->sync(array_unique(array_map('intval', $terminIds)));
            }

            app(FrontendRevalidationService::class)->queuePostChange(
                $post->getKey(),
                $post->frontendRevalidationOriginalSnapshot
            );

            $post->frontendRevalidationOriginalSnapshot = null;
        });

        static::deleted(function ($post) {
            $searchIndexManager = app(SearchIndexManager::class);
            $searchIndexName = $post->searchableAs();

            if (
                $searchIndexManager->aliasExists($searchIndexName)
                || $searchIndexManager->indexExists($searchIndexName)
            ) {
                $post->unsearchable();
            }

            app(FrontendRevalidationService::class)->queuePostChange(
                $post->getKey(),
                $post->frontendRevalidationOriginalSnapshot
                    ?? app(FrontendCacheTagService::class)->snapshotPost($post)
            );

            $post->frontendRevalidationOriginalSnapshot = null;
        });

        static::creating(function ($post) {
            if ($post->views_count === null) {
                $post->views_count = 0;
            }
        });

        if (defined('static::TYPE')) {
            static::creating(function ($post) {
                $post->type = static::TYPE;
            });
        
            static::updating(function ($post) {
                $post->type = static::TYPE;
            });
        }
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($attributes['type']) && $attributes['type'] == PostTypes::ONLINE) {
                    $content = [];
                    foreach ($this->onlineMessages as $message) {
                        $content[$message->id] = [
                            'type' => 'online',
                            'attributes' => [
                                'published_at' => $message->published_at,
                                'is_key_event' => $message->is_key_event,
                                'outline' => $message->outline,
                                'text' => $message->text,
                                'images' => $message->images,
                                'video_url' => $message->video_url,
                                'video_description' => $message->video_description,
                                'video_author' => $message->video_author,
                                'embed_code' => $message->embed_code,
                                'embed_type' => $message->embed_type,
                            ],
                        ];
                    }
                    return $content;
                } else {
                    return (new CompactFlexibleCast)->get($this, '', $value, $attributes);
                }
            },
        );
    }
    
    public function getAuthorAttribute() {
        $authors = $this->authors();
        if ($authors->count() > 0) {
            return $authors->first()->fullname;
        } else {
            return null;
        }
    } 

    public function getImageUrlAttribute()
    {
        if (empty($this->image)) {
            return null;
        }
        
        if (str_starts_with($this->image, 'post_cover/')) {
            return $this->getImageUrl(ImageService::SIZE_ORIGINAL, includeDomain: false);
        } else {
            return null;
        }
    }

    // Проверка доступа

    public function isOwner($user_id) 
    {
        return in_array($user_id, $this->owners->pluck('id')->toArray());
    }

    // public static function checkAccessByRoles($roles) 
    // {
    //     $access = PostTypes::access();
    //     if (!empty($access[static::TYPE])) {
    //         foreach ($roles as $role) {
    //             if (in_array($role, $access[static::TYPE])) {
    //                 return true;
    //             }
    //         }
    //     }
    //     return false;
    // }

    // Связи

    public function translation() {
        return $this->belongsTo(Post::class, 'translation_id');
    }

    public function histories()
    {
        return $this->hasMany(PostHistory::class);
    }

    public function onlineMessages()
    {
        return $this->hasMany(OnlineMessage::class, 'post_id')->orderBy('published_at', 'desc');
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function investigationTheme() {
        return $this->belongsTo(InvestigationTheme::class);
    }

    public function columnist() {
        return $this->belongsTo(Author::class, 'columnist_id');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'post_authors', 'post_id', 'author_id')->withPivot('position')->orderBy('post_authors.position');
    }
    
    public function owners() {
        return $this->belongsToMany(User::class, 'post_owners', 'post_id');
    }

    public function tags() {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id');
    }

    public function termins() {
        return $this->belongsToMany(Termin::class, 'post_termins', 'post_id');
    }

    public function collections() {
        return $this->hasMany(CollectionPost::class, 'post_id');
    }

    //

    public function incrementViewsCount()
    {
        $this->timestamps = false;

        self::where('id', $this->id)
            ->increment('views_count');

        $this->views_count++;
        $this->timestamps = true;
    }

    public function createImageVariants()
    {
        $imagePath = $this->image;

        if (empty($imagePath)) {
            return;
        }

        // Nova uploads the image before the post ID is assigned during creation,
        // resulting in a malformed path like "post_cover/original///filename.jpg".
        // After the post is saved and the ID is available, move the file to the correct path.
        $correctDir = ImageService::getImagePath($this->id, ImageService::TYPE_POST_COVER, ImageService::SIZE_ORIGINAL);
        $filename = basename($imagePath);
        $correctPath = $correctDir . '/' . $filename;

        if ($imagePath !== $correctPath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->move($imagePath, $correctPath);
            $this->image = $correctPath;
            $this->saveQuietly();
            $imagePath = $correctPath;
        }

        ImageService::createImageVariants($this->id, $imagePath);
        ShareImageService::generate($this);
    }

    public function getImageUrl($size = ImageService::SIZE_ORIGINAL, bool $includeDomain = false)
    {
        return ImageService::getImageUrl($this->id, $this->image, ImageService::TYPE_POST_COVER, $size, $includeDomain);
    }

    public function getPath() {
        $columnistPrefix = ($this->type === PostTypes::OPINION && $this->columnist)
            ? "{$this->columnist->slug}/"
            : '';
        return '/'
            . ($this->language_code === 'ru' ? $this->category->slug . '/' : "{$this->language_code}/{$this->category->slug}/")
            . $columnistPrefix
            . "{$this->slug}";
    }

    /**
     * Scout / Elasticsearch methods
     */

    /**
     * Определяет, какие данные индексировать в Elasticsearch
     */
    public function toSearchableArray(): array
    {
        // Извлекаем текстовое содержимое из flexible content
        $contentText = $this->extractTextFromContent($this->content);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'lead' => $this->lead ?? '',
            'content' => $contentText,
            'category_id' => $this->category_id,
            'category_title' => $this->category?->title ?? '',
            'category_slug' => $this->category?->slug ?? '',
            'language_code' => $this->language_code,
            'type' => $this->type,
            'status' => $this->status,
            'published_at' => $this->published_at?->timestamp,
            'views_count' => $this->views_count ?? 0,
            'authors' => $this->authorsForSearchIndex()->pluck('fullname')->values()->all(),
            'columnist' => $this->columnistNameForSearchIndex(),
            'tags' => $this->tags->pluck('title')->toArray(),
        ];
    }

    /**
     * Название индекса для поиска
     */
    public function searchableAs(): string
    {
        return 'posts';
    }

    /**
     * Индексировать только опубликованные статьи
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Извлекает текст из flexible content для индексации
     */
    private function extractTextFromContent($content): string
    {
        if (empty($content)) {
            return '';
        }

        $text = '';
        
        if (is_array($content)) {
            foreach ($content as $block) {
                if (isset($block['attributes'])) {
                    $attrs = $block['attributes'];
                    
                    // Text блоки
                    if (isset($attrs['text'])) {
                        $text .= strip_tags($attrs['text']) . ' ';
                    }
                    
                    // Quote блоки
                    if (isset($attrs['quote'])) {
                        $text .= strip_tags($attrs['quote']) . ' ';
                    }
                    
                    // Title блоки
                    if (isset($attrs['title'])) {
                        $text .= strip_tags($attrs['title']) . ' ';
                    }
                    
                    // Subtitle блоки
                    if (isset($attrs['subtitle'])) {
                        $text .= strip_tags($attrs['subtitle']) . ' ';
                    }
                }
            }
        }
        
        // Возвращаем весь контент без ограничений
        return trim($text);
    }

    /**
     * Авторы, чьи ФИО допустимо искать так же, как они показываются в API (PostResource).
     */
    private function authorsForSearchIndex(): \Illuminate\Support\Collection
    {
        if (!$this->shouldExposeAuthorsInPublication()) {
            return collect();
        }

        return $this->authors->filter(
            fn (Author $author) => !in_array($this->type, $author->post_types_with_hidden_author_name ?? [], true)
                || $this->author_visibility === 'force_shown'
        );
    }

    private function columnistNameForSearchIndex(): string
    {
        if (!$this->shouldExposeColumnistInPublication()) {
            return '';
        }

        return $this->columnist?->fullname ?? '';
    }

    /** См. PostResource::shouldShowAuthors */
    private function shouldExposeAuthorsInPublication(): bool
    {
        if ($this->type === PostTypes::OPINION) {
            return false;
        }

        if ($this->author_visibility === 'force_hidden') {
            return false;
        }

        return true;
    }

    /** См. PostResource::shouldShowColumnist */
    private function shouldExposeColumnistInPublication(): bool
    {
        if ($this->columnist === null) {
            return false;
        }

        if ($this->type !== PostTypes::OPINION) {
            return false;
        }

        if ($this->author_visibility === 'force_hidden') {
            return false;
        }

        if ($this->author_visibility !== 'force_shown'
            && in_array($this->type, $this->columnist->post_types_with_hidden_author_name ?? [], true)
        ) {
            return false;
        }

        return true;
    }
}