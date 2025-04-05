<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

use Carbon\Carbon;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

use App\Services\ImageService;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    private $locale;

    //protected $appends = ['name'];
    protected $fillable = [
        'email',
        'password',

        'name',
        'avatar',

        'role_code',
        'available_languages',
        'timezone',

        //  'slug',
        // 'ru_first_name',
        // 'en_first_name',
        // 'ru_second_name',
        // 'en_second_name',
        // 'ru_description',
        // 'en_description',
        // 'position',
        // 'twitter',
        // 'facebook',
        // 'hide_author_name_in_all_news',
        // 'hide_author_page',
        // 'hide_columnist_page',
        // 'is_authentication_disabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role_code' => 'string',
        'available_languages' => 'array',
        // 'hide_author_name_in_all_news' => 'boolean',
        // 'hide_author_page' => 'boolean',
        // 'hide_columnist_page' => 'boolean',
        // 'is_authentication_disabled' => 'boolean',
    ];

    // public static function boot() {
    //     parent::boot();

    //     static::saving(function ($user) {
    //         $updated = false;
        
    //         if (empty($user->email) || empty($user->password)) {
    //             $user->is_authentication_disabled = true;
    //             $updated = true;
    //         }
        
    //         if ($updated) {
    //             $user->saveQuietly();
    //         }
    //     });   
    // }

    public function __construct($attributes = []) {
        parent::__construct($attributes);
        $this->locale = app()->getLocale();
    }

    public function getLocale() {
        return $this->locale;
    }

    public function setLocale($locale) {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Проверка роли пользователя.
     */
    public function isAdmin() {
        return $this->role_code === UserRoles::ADMIN;
    }

    public function isEditor() {
        return $this->role_code === UserRoles::EDITOR;
    }

    public function isJournalist() {
        return $this->role_code === UserRoles::JOURNALIST;
    }



     
    public function hasRole($roles) {
        if (!is_array($roles))
            $roles = [$roles];
        
        return array_intersect($roles, $this->roles ?? []);
    }

    public function canViewAll() {
        return $this->hasRole([
            UserRoles::ADMIN,
            UserRoles::EDITOR,
        ]);
    }

    public function canDeleteAll() {
        return $this->hasRole([
            UserRoles::ADMIN,
        ]);
    }

    /**
     * Имя и описание
     */
    // public function getFullNameAttribute() {
    //     $firstName = $this->{"{$this->locale}_first_name"};
    //     $secondName = $this->{"{$this->locale}_second_name"};
    //     return trim("{$firstName} {$secondName}");
    // }

    // public function getFirstNameAttribute() {
    //     return $this->{"{$this->locale}_first_name"};
    // }

    // public function getSecondNameAttribute() {
    //     return $this->{"{$this->locale}_second_name"};
    // }

    // public function getDescriptionAttribute() {
    //     return $this->{"{$this->locale}_description"};
    // }
    
    public function getAvatarUrlAttribute() {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function getAvatarCroppedUrlAttribute() {
        return ImageService::getImageUrl($this->id, $this->avatar, ImageService::TYPE_USER_PHOTO, ImageService::SIZE_CROPPED);
    }

    /**
     * Публикации пользователя
     */

     public function posts()
    {    
        return $this->belongsToMany(Post::class, 'post_owners', 'user_id', 'post_id');
    }

    // public function posts()
    // {    
    //     return $this->belongsToMany(Post::class, 'post_authors', 'user_id', 'post_id');
    // }

    // public function opinions($language_code = 'ru') {
    //     return $this->hasMany(Post::class, 'columnist_id', 'id')
    //         ->where('language_code', $language_code)
    //         ->where('type', PostTypes::OPINION);
    // }

    // public function notOpinions($language_code = 'ru') {
    //     return $this->belongsToMany(Post::class, 'post_authors', 'user_id', 'post_id')
    //         ->where('language_code', $language_code)
    //         ->whereIn('type', [PostTypes::ARTICLE, PostTypes::NEWS, PostTypes::ONLINE]);
    // }


    // public function articles() {
    //     return $this->belongsToMany(Post::class, 'post_authors', 'user_id', 'post_id')
    //         ->where('type', PostTypes::ARTICLE);
    // }

    // public function news() {
    //     return $this->belongsToMany(Post::class, 'post_authors', 'user_id', 'post_id')
    //         ->where('type', PostTypes::NEWS);
    // }


    // ?????
    // public function postHistories()
    // {
    //     return $this->hasMany(PostHistory::class);
    // }

    /**
     * Рейтинг авторов
     */
    // public static function getTopUsersByPeriod($postType, $limit = 10, $days = 30)
    // {
    //     $access = PostTypes::access();
    //     $userRoles = $access[$postType];

    //     $startDate = Carbon::now()->subDays($days);
    //     $endDate = Carbon::now();

    //     $query = self::query();
        
    //     if ($postType === PostTypes::OPINION) {
    //         $query->withCount(['opinions as posts_count' => function ($query) use ($startDate, $endDate) {
    //             $query->whereBetween('published_at', [$startDate, $endDate]);
    //         }]);
    //     } else {
    //         $query->withCount(['posts as posts_count' => function ($query) use ($postType, $startDate, $endDate) {
    //             $query
    //                 ->where('language_code', app()->getLocale())
    //                 ->where('type', $postType)
    //                 ->whereBetween('published_at', [$startDate, $endDate]);
    //         }]);
    //     }
        
    //     return $query
    //         ->orderByDesc('posts_count')
    //         ->limit($limit)
    //         ->get();
    // }

    public static function getColumnistsWithMinPosts($minPosts = 5)
    {
        $users = self::query()
            //->whereJsonContains('roles', UserRoles::COLUMNIST)
            ->withCount(['opinions as posts_count' => function ($query) {
                $query->where('language_code', app()->getLocale())
                      ->where('type', PostTypes::OPINION);
            }])
            ->orderByDesc('posts_count')
            ->get();
        
        return $users->filter(function ($user) use ($minPosts) {
            return $user->posts_count >= $minPosts;
        })->values();
    }
}
