<?php

namespace App\Models\PostTypes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;

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

    public function online() {
        return $this->belongsTo(PostOnline::class, 'post_id');
    }
}