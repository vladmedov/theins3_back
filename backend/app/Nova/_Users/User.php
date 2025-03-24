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

//use Outl1ne\NovaSortable\Traits\HasSortableManyToManyRows;

class User extends Resource
{
    // use HasSortableManyToManyRows;
    // public static $sortableCacheEnabled = false;

    // public $sortable = [
    //     'only_sort_on' => \App\Nova\_Posts\Post::class,
    // ];

    public static $model = \App\Models\User::class;

    public static $title = 'full_name';
    public static $search = ['id', 'email', 'slug', 'ru_first_name', 'en_first_name', 'ru_second_name', 'en_second_name'];

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

            Badge::make(__('Disable Authentication'), 'is_authentication_disabled')->map([
                1 => 'danger',
                0 => 'success',
            ])->label(function ($value) {
                if ($value) {
                    return __('No access');
                } else {
                    return __('Has access');
                }
            })
            ->sortable(),

            Panel::make(__('Main Information'), [
                Boolean::make(__('Disable Authentication'), 'is_authentication_disabled')
                    ->onlyOnForms()
                    ->sortable()
                    ->default(1)
                    ->help(__('If enabled, the user will be unable to log in.')),
        
                Slug::make(__('Slug'), 'slug')
                    ->onlyOnForms()
                    ->from('ru_second_name')
                    ->sortable()
                    ->rules('required', 'max:255'),

                Email::make(__('Email'), 'email')
                    ->sortable()
                    ->dependsOn(
                        ['is_authentication_disabled'],
                        function (Email $field, NovaRequest $request, FormData $formData) {
                            if ($formData->is_authentication_disabled) {
                                $field->rules('nullable', 'email', 'max:255');
                                $field->hide();
                            } else {
                                $field->rules('required', 'email', 'max:255');
                                $field->show();
                            }
                        }
                    ),
            
                Password::make(__('Password'), 'password')
                    ->onlyOnForms()
                    ->dependsOn(
                        ['is_authentication_disabled'],
                        function (Password $field, NovaRequest $request, FormData $formData) {
                            if ($formData->is_authentication_disabled) {
                                $field->rules('nullable', 'min:8');
                                $field->hide();
                            } else {
                                $field->rules('required', 'min:8');
                                $field->show();
                            }
                        }
                    ),

                MultiSelect::make(__('Roles'), 'roles')
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
            ]),
        
            Panel::make(__('User Information'), [
                Text::make(__('First name (RU)'), 'ru_first_name')
                    ->onlyOnForms()
                    ->rules('required', 'max:255'),
                    
                Text::make(__('Last name (RU)'), 'ru_second_name')
                    ->onlyOnForms()
                    ->rules('required', 'max:255'),

                Text::make(__('First name (EN)'), 'en_first_name')
                    ->onlyOnForms()
                    ->rules('required', 'max:255'),

                Text::make(__('Last name (EN)'), 'en_second_name')
                    ->onlyOnForms()
                    ->rules('required', 'max:255'),
                    
                Textarea::make(__('Description (RU)'), 'ru_description')
                    ->onlyOnForms(),
                    
                Textarea::make(__('Description (EN)'), 'en_description')
                    ->onlyOnForms(),

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

                Text::make(__('Job position'), 'position')
                    ->onlyOnForms()
                    ->rules('nullable', 'max:255'),
                    
                Text::make('Twitter', 'twitter')
                    ->onlyOnForms()
                    ->rules('nullable', 'max:255'),
                    
                Text::make('Facebook', 'facebook')
                    ->onlyOnForms()
                    ->rules('nullable', 'max:255'),
            ]),

            Text::make(__('Full name'), 'full_name')
                ->sortable()
                ->exceptOnForms(),

            Panel::make(__('Display Settings'), [
                Boolean::make(__('Hide author name in all news'), 'hide_author_name_in_all_news')
                    ->onlyOnForms()
                    ->sortable(),

                Boolean::make(__('Hide author page'), 'hide_author_page')
                    ->onlyOnForms()
                    ->sortable()
                    ->help(__('Does not affect users who have only the columnist role.')),

                Boolean::make(__('Hide columnist page'), 'hide_columnist_page')
                    ->onlyOnForms()
                    ->sortable()
                    ->help(__('Does not affect users who do not have the columnist role.')),
            ]),

            Text::make($this->getPostsCountTitle(), function () {
                return $this->getPostsCount();
            }),

           

            DateTimeSplit::make(__('Created'), 'created_at')->onlyOnDetail(),
            DateTimeSplit::make(__('Updated'), 'updated_at')->onlyOnDetail(),
                
        ], array_filter([
            array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::AUTHOR], $request->user()->roles) 
                ? BelongsToMany::make(__('Articles'), 'articles', PostArticle::class)
                    ->showCreateRelationButton()->searchable()->relatableQueryUsing(function ($request, $query) {
                        $query->where('title', 'like', "%{$request->search}%");
                    })
                : null,
        
            array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::NEWS_WRITER], $request->user()->roles) 
                ? HasMany::make(__('News'), 'news', PostNews::class) 
                : null,
        
            array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::COLUMNIST], $request->user()->roles) 
                ? HasMany::make(__('Opinions'), 'opinions', PostOpinion::class) 
                : null,
        ]));
        
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
            $query->whereJsonContains('roles', static::getUserRole());
        }

        return $query;
    }

    public function subtitle() {
        return trim("{$this->ru_first_name} {$this->ru_second_name} ({$this->en_first_name} {$this->en_second_name})");
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
