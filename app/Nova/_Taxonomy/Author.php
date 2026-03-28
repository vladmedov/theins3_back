<?php

namespace App\Nova\_Taxonomy;

use App\Support\Nova\FormActionBar;
use App\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use Laravel\Nova\Nova;
use Laravel\Nova\Panel;

use Laravel\Nova\Fields\Hidden;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BelongsTo;

use App\Nova\_Users\User as NovaUser;
use App\Services\ImageService;
use App\Enums\PostTypes;
class Author extends Resource
{
    public static $model = \App\Models\Author::class;

    public static $title = 'full_name';
    public static $search = ['id', 'first_name', 'last_name', 'slug'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        $locale = $this->effectiveResourceLanguageCode();

        $generalFields = [
            Slug::make('Slug', 'slug')
                ->from('last_name', 'last_name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('First name'), 'first_name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('Last name'), 'last_name')
                ->sortable()
                ->rules('required', 'max:255'),

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

            Text::make(__('Posts count'), 'posts_count')
                ->onlyOnIndex()
                ->sortable(),
        ];

        return array_merge([
            Hidden::make(__('Language code'), 'language_code')
                ->default($locale),

            FormActionBar::make([
                'metaBlock' => $this->resource?->exists ? [
                    'items' => [
                        [
                            'label' => __('form_action_bar.created_at'),
                            'date' => $this->resource->created_at,
                        ],
                        [
                            'label' => __('form_action_bar.updated_at'),
                            'date' => $this->resource->updated_at,
                        ],
                    ],
                ] : null,
                'saveAction' => [
                    'label' => $this->exists ? __('Save') : __('Create'),
                ],
            ]),

            Avatar::make(__('Photo'), 'avatar')
                ->disk('public')
                ->onlyOnIndex()
                ->preview(function ($value, $disk) {
                    $model = $this->resource ?? null;
                    if (!$model || !$value) return null;
                    $url = $model->avatar_url;
                    if (!$url) return null;
                    return str_starts_with($url, 'http') ? $url : rtrim(config('app.url'), '/') . $url;
                }),

            Panel::make(__('General'), $generalFields),

            Panel::make(__('Display Settings'), [

                MultiSelect::make(__('Allowed post types'), 'allowed_post_types')
                    ->options(PostTypes::all())
                    ->nullable()
                    ->onlyOnForms()
                    ->help(__('Types of publications where this author can be selected as the author of the content.')),

                MultiSelect::make(__('Post types with hidden author name'), 'post_types_with_hidden_author_name')
                    ->options(PostTypes::all())
                    ->nullable()
                    ->onlyOnForms()
                    ->help(__('Types of publications where the author name is hidden unless the publication is configured to show all authors.')),

                Boolean::make(__('Hide author page'), 'is_author_page_hidden')
                    ->onlyOnForms()
                    ->sortable()
                    ->help(__('Does not affect users who have no published news, articles, or online broadcasts.')),

                Boolean::make(__('Hide columnist page'), 'is_columnist_page_hidden')
                    ->onlyOnForms()
                    ->sortable()
                    ->help(__('Does not affect users who have no published opinions.')),
            ]),

            Panel::make(__('User Information'), [
                BelongsTo::make(__('User'), 'user', NovaUser::class)
                    ->searchable()
                    ->nullable()
                    ->sortable(),

                Textarea::make(__('Description'), 'description')
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

            // Text::make($this->getPostsCountTitle(), function () {
            //     return $this->getPostsCount();
            // }),

            // Text::make(__('Posts count'), function () {
            //     return $this->posts()->count();
            // }),
        ], []
        // array_filter([
        //     array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::AUTHOR], $request->user()->roles) 
        //         ? BelongsToMany::make(__('Articles'), 'articles', PostArticle::class)
        //             ->showCreateRelationButton()->searchable()->relatableQueryUsing(function ($request, $query) {
        //                 $query->where('title', 'like', "%{$request->search}%");
        //             })
        //         : null,
        
        //     array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::NEWS_WRITER], $request->user()->roles) 
        //         ? HasMany::make(__('News'), 'news', PostNews::class) 
        //         : null,
        
        //     array_intersect([UserRoles::ADMIN, UserRoles::EDITOR, UserRoles::COLUMNIST], $request->user()->roles) 
        //         ? HasMany::make(__('Opinions'), 'opinions', PostOpinion::class) 
        //         : null,
        // ])
    
        );
    }

    public static function label() {
        return __('Authors');
    }
    
    public static function singularLabel() {
        return __('Author');
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
        return $query
            ->where('language_code', static::resolveResourceLanguageCodeForRequest($request))
            ->withCount('posts');
    }

    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->where('language_code', static::resolveResourceLanguageCodeForRequest($request))
            ->whereJsonContains('allowed_post_types', [$request->newResource()->getPostType()]);
    }

    public static function createButtonLabel(): string
    {
        return __('Create');
    }

    public static function updateButtonLabel(): string
    {
        return __('Save');
    }
}
