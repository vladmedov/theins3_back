<?php

namespace App\Models\PostTypes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Services\FrontendRevalidationService;

class OnlineMessage extends Model
{
    use HasFactory;

    protected $table = 'online_messages';

    /**
     * Массив полей, которые можно массово заполнять.
     */
    protected $fillable = [
        'language_code',
        'post_id',
        'published_at',
        'is_key_event',
        'outline',
        'text',
        'images',
        'video_url',
        'video_description',
        'video_author',
        'embed_code',
        'embed_type',
    ];

    /**
     * Преобразование типов для полей.
     */
    protected $casts = [
        'published_at' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'is_key_event' => 'boolean',
        'images' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $message): void {
            $service = app(FrontendRevalidationService::class);

            if ($message->wasChanged('post_id')) {
                $originalPostId = (int) ($message->getOriginal('post_id') ?? 0);
                if ($originalPostId > 0) {
                    $service->queuePostChange($originalPostId);
                }
            }

            $currentPostId = (int) ($message->post_id ?? 0);
            if ($currentPostId > 0) {
                $service->queuePostChange($currentPostId);
            }
        });

        static::deleted(function (self $message): void {
            $postId = (int) ($message->post_id ?? 0);
            if ($postId > 0) {
                app(FrontendRevalidationService::class)->queuePostChange($postId);
            }
        });
    }

    public function online() {
        return $this->belongsTo(PostOnline::class, 'post_id');
    }
}