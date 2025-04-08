<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

use Carbon\Carbon;

use Whitecube\NovaFlexibleContent\Value\FlexibleCast;
use App\Casts\CompactFlexibleCast;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;
use \App\Models\PostTypes\OnlineMessage;

use App\Services\ChangeDetectorService;
use App\Services\ImageService;

use Illuminate\Support\Facades\Log;

// use Spatie\MediaLibrary\HasMedia;
// use Spatie\MediaLibrary\InteractsWithMedia;
// use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model { //implements HasMedia {

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
    ];

    protected $casts = [
        'category_id' => 'integer',
        'created_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'updated_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'published_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'views_count' => 'integer',
        'content' => CompactFlexibleCast::class,
        'author_visibility' => 'string',
    ];

    public static function boot() {
        parent::boot();

        // Объединяем логику создания PostHistory в отдельный метод
        $createHistory = function($post, $oldData, $newData, $status) {
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
            
            if (!empty($post->image)) {
                $post->createImageVariants();
            }
        });

        static::updating(function ($post) use ($createHistory) {
            $createHistory($post, $post->getOriginal(), $post->getAttributes(), 'updated');
        });
        
        static::updated(function ($post) {
            if ($post->wasChanged('image') && !empty($post->image)) {
                $post->createImageVariants();
            }
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

    public function getImageUrlAttribute() {
        return $this->image ? 'https://insidertexts.com/storage/post/' . $this->id . '/' . $this->image : null; // TODO: remove
        //return $this->image ? Storage::disk('public')->url($this->image) : null;
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
        ImageService::createImageVariants($this->id, $this->image);
    }

    public function getImageUrl($size = ImageService::SIZE_ORIGINAL)
    {
        return ImageService::getImageUrl($this->id, $this->image, ImageService::TYPE_POST_COVER, $size);
    }

    public function getPath() {
        return '/'
            . ($this->language_code === 'ru' ? $this->category->slug . '/' : "{$this->language_code}/{$this->category->slug}/")
            . "{$this->slug}";
    }
}