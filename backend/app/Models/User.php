<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Carbon\Carbon;

use \App\Enums\UserRoles;
use \App\Enums\PostTypes;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $appends = ['fullname'];
    protected $fillable = [
        'email',
        'password',
        'slug',
        'available_languages',
        'ru_first_name',
        'en_first_name',
        'ru_second_name',
        'en_second_name',
        'ru_description',
        'en_description',
        'roles',
        'position',
        'twitter',
        'facebook',
        'avatar',
        'hide_author_name_in_all_news',
        'hide_author_page',
        'hide_columnist_page',
        'is_authentication_disabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'roles' => 'array',
        'available_languages' => 'array',
        'hide_author_name_in_all_news' => 'boolean',
        'hide_author_page' => 'boolean',
        'hide_columnist_page' => 'boolean',
        'is_authentication_disabled' => 'boolean',
    ];

    public static function boot() {
        parent::boot();

        static::saving(function ($user) {
            $updated = false;
        
            if (empty($user->email) || empty($user->password)) {
                $user->is_authentication_disabled = true;
                $updated = true;
            }
        
            if ($updated) {
                $user->saveQuietly();
            }
        });   
    }

    /**
     * Проверка роли пользователя.
     */
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
    public function getFullNameAttribute() {
        $locale = app()->getLocale();
        $firstName = $this->{"{$locale}_first_name"};
        $secondName = $this->{"{$locale}_second_name"};
        return trim("{$firstName} {$secondName}");
    }

    public function getFirstNameAttribute() {
        $locale = app()->getLocale();
        return $this->{"{$locale}_first_name"};
    }

    public function getSecondNameAttribute() {
        $locale = app()->getLocale();
        return $this->{"{$locale}_second_name"};
    }

    public function getDescriptionAttribute() {
        $locale = app()->getLocale();
        return $this->{"{$locale}_description"};
    }

    /**
     * Публикации пользователя
     */
    public function posts()
    {    
        return $this->belongsToMany(Post::class, 'post_authors', 'user_id', 'post_id');
    }

    public function articles() {
        return $this->belongsToMany(Post::class, 'post_authors')
            ->where('type', PostTypes::ARTICLE);
    }

    public function news() {
        return $this->belongsToMany(Post::class, 'post_authors')
            ->where('type', PostTypes::NEWS);
    }

    public function opinions() {
        return $this->hasMany(Post::class, 'columnist_id', 'id')
            ->where('type', PostTypes::OPINION);
    }

    public function postHistories()
    {
        return $this->hasMany(PostHistory::class);
    }

    /**
     * Выборка
     */
    public static function getTopUsersByPeriod($postType, $limit = 10, $days = 30)
    {
        $access = PostTypes::access();
        $userRoles = $access[$postType];

        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        return self
            ::query()
            //::whereJsonContains('roles', $userRoles)
            ->withCount(['posts as posts_count' => function ($query) use ($postType, $startDate, $endDate) {
                $query
                    ->where('language_code', app()->getLocale())
                    ->where('type', $postType)
                    ->whereBetween('published_at', [$startDate, $endDate]);
            }])
            ->orderByDesc('posts_count')
            ->limit($limit)
            ->get();
    }
}
