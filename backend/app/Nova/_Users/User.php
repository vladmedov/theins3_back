<?php

namespace App\Nova\_Users;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\FormData;
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

use Medov\DateTimeSplit\DateTimeSplit;

use App\Enums\UserRoles;

use App\Nova\_Posts\PostArticle;
use App\Nova\_Posts\PostNews;
use App\Nova\_Posts\PostOpinion;
use App\Nova\_Posts\PostOnline;

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

            Badge::make(__('Disable Authentication'), 'is_authentication_disabled')->map([
                1 => 'danger',
                0 => 'success',
            ])->label(function ($value) {
                if ($value) {
                    return 'Нет доступа';
                } else {
                    return 'Имеет доступ';
                }
            })
            ->sortable(),

            Boolean::make(__('Disable Authentication'), 'is_authentication_disabled')
                ->onlyOnForms()
                ->sortable()
                ->help(__('If enabled, the user will be unable to log in.')),
        
            Text::make('Имя (RU)', 'ru_first_name')
                ->onlyOnForms()
                ->rules('required', 'max:255'),
                
            Text::make('Фамилия (RU)', 'ru_second_name')
                ->onlyOnForms()
                ->rules('required', 'max:255'),
                
            Textarea::make('Описание (RU)', 'ru_description')
                ->onlyOnForms(),

            Text::make('Имя (EN)', 'en_first_name')
                ->onlyOnForms()
                ->rules('required', 'max:255'),

            Text::make('Фамилия (EN)', 'en_second_name')
                ->onlyOnForms()
                ->rules('required', 'max:255'),
                
            Textarea::make('Описание (EN)', 'en_description')
                ->onlyOnForms(),

            Text::make('Полное имя', 'full_name')
                ->sortable()
                ->exceptOnForms(),

            Slug::make('Slug', 'slug')
                ->from('ru_second_name')
                ->sortable()
                ->rules('required', 'max:255'),
        
            Email::make('Email', 'email')
                ->sortable()
                ->rules('nullable', 'email', 'max:255'),
        
            Password::make('Пароль', 'password')
                ->onlyOnForms()
                ->rules('nullable', 'min:8'),

            MultiSelect::make('Роли', 'roles')
                ->options(UserRoles::all())
                ->rules('required'),

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

            Text::make($this->getPostsCountTitle(), function () {
                return $this->getPostsCount();
            }),

            // Язык по умолчанию
            BooleanGroup::make('Доступные языки', 'available_languages')
                ->options([
                    'ru' => 'Русский',
                    'en' => 'English'
                ])
                ->rules('required', function($attribute, $value, $fail) {
                    $decodedValue = json_decode($value, true);
                    if (!is_array($decodedValue) || !array_filter($decodedValue)) {
                        $fail(__('Choose at least one language.'));
                    }
                })
                ->help(__('Choose at least one language.')),

            DateTimeSplit::make('Создан', 'created_at')->onlyOnDetail(),
            DateTimeSplit::make('Обновлён', 'updated_at')->onlyOnDetail(),
        ], array_filter([
            array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::AUTHOR], $request->user()->roles) 
                ? BelongsToMany::make('Статьи', 'articles', PostArticle::class)
                    ->showCreateRelationButton()->searchable()->relatableQueryUsing(function ($request, $query) {
                        $query->where('title', 'like', "%{$request->search}%");
                    })
                : null,
        
            array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::NEWS_WRITER], $request->user()->roles) 
                ? HasMany::make('Новости', 'news', PostNews::class) 
                : null,
        
            array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::COLUMNIST], $request->user()->roles) 
                ? HasMany::make('Мнения', 'opinions', PostOpinion::class) 
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
