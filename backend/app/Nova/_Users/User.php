<?php

namespace App\Nova\_Users;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Avatar;

use Medov\DateTimeSplit\DateTimeSplit;

use App\Enums\UserRoles;

use App\Nova\_Posts\PostArticle;
use App\Nova\_Posts\PostNews;
use App\Nova\_Posts\PostOpinion;
use App\Nova\_Posts\PostOnline;

use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

// use Ultrasimplified\ImageCropper\ImageCropper;

// use Marshmallow\AdvancedImage\AdvancedImage;

// use Slim\Image\Image;

use Laravel\Nova\Fields\Image;

use DigitalCreative\Filepond\Filepond;

//use Outl1ne\NovaSortable\Traits\HasSortableManyToManyRows;

class User extends Resource
{
    // use HasSortableManyToManyRows;
    // public static $sortableCacheEnabled = false;

    // public $sortable = [
    //     'only_sort_on' => \App\Nova\_Posts\Post::class,
    // ];

    public static $model = \App\Models\User::class;

    public static $title = 'name';
    public static $search = ['id', 'email', 'slug', 'name'];

    public static $clickAction = 'edit';
    
    public function fields(NovaRequest $request) {
        return array_merge([
            ID::make()->onlyOnDetail(),

            Avatar::make(__('Photo'), 'avatar')
                ->disk('public')
                ->rules('nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120')
                ->path(ImageService::getImagePath($this->id, ImageService::TYPE_USER_PHOTO, ImageService::SIZE_ORIGINAL))
                ->preview(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                })
                ->thumbnail(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                })
                ->prunable()
                ->exceptOnForms(),

            Text::make(__('Name (RU)'), 'name')
                ->rules('required', 'max:255'),

            Email::make(__('Email'), 'email')
                ->sortable(),
        
            Password::make(__('Password'), 'password')
                ->onlyOnForms(),

            Select::make(__('Role'), 'role_code')
                ->options(UserRoles::all())
                ->rules('required'),

            BooleanGroup::make(__('Available languages'), 'available_languages')
                ->onlyOnForms()
                ->options([
                    'ru' => __('Russian'),
                    'en' => __('English')   
                ])
                ->rules('required', function($attribute, $value, $fail) {
                    $decodedValue = json_decode($value, true);
                    if (!is_array($decodedValue) || !array_filter($decodedValue)) {
                        $fail(__('Choose at least one language.'));
                    }
                })
                ->help(__('Choose at least one language.')),

            Avatar::make(__('Photo'), 'avatar')
                ->disk('public')
                ->rules('nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120')
                ->path(ImageService::getImagePath($this->id, ImageService::TYPE_USER_PHOTO, ImageService::SIZE_ORIGINAL))
                ->preview(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                })
                ->thumbnail(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                })
                ->prunable()
                ->onlyOnForms(),

            Select::make(__('Timezone'), 'timezone')
                ->options(function () {
                    $allTimezones = timezone_identifiers_list();
                    $timezones = array_combine($allTimezones, $allTimezones);
                    
                    $priorityTimezones = [
                        'Europe/Moscow' => 'Europe/Moscow (MSK)',
                        'Europe/Warsaw' => 'Europe/Warsaw (CET)',
                        'Europe/Riga' => 'Europe/Riga (EEST)',
                        'UTC' => 'UTC'
                    ];
                    
                    foreach (array_keys($priorityTimezones) as $tz) {
                        if (isset($timezones[$tz])) {
                            unset($timezones[$tz]);
                        }
                    }
                    
                    return $priorityTimezones + $timezones;
                })
                ->searchable()
                ->rules('required')
                ->default('Europe/Moscow')
                ->help(__('Select your local timezone')),   

            DateTimeSplit::make(__('Created'), 'created_at')->onlyOnDetail(),
            DateTimeSplit::make(__('Updated'), 'updated_at')->onlyOnDetail(),
                
        ]
       );
        
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if (static::getUserRole()) {
            $query->where('role_code', static::getUserRole());
        }

        return $query;
    }

    public function subtitle() {
        return trim("{$this->name} ({$this->email})");
    }

    public static function label() {
        return __('All users');
    }
    
    public static function singularLabel() {
        return __('User');
    }

    public function getPostsCountTitle() {
        return __('Posts count');
    }

    public function getPostsCount() {
        return $this->posts()->count();
    }

    protected static function getUserRole() {
        return false;
    }

    protected static function getPostType() {
        return false;
    }
}
