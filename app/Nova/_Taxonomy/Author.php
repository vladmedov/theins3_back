<?php

namespace App\Nova\_Taxonomy;

use Laravel\Nova\Resource;

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

use App\Services\ImageService;
use App\Enums\PostTypes;
class Author extends Resource
{
    public static $model = \App\Models\Author::class;

    public static $title = 'full_name';
    public static $search = ['id', 'first_name', 'last_name', 'slug'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        return array_merge([
            Hidden::make(__('Language code'), 'language_code')
                ->default(app()->getLocale()),

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

            Panel::make(__('Display Settings'), [

                MultiSelect::make(__('Allowed post types'), 'allowed_post_types')
                    ->options(PostTypes::all())
                    ->nullable()
                    ->onlyOnForms(),

                MultiSelect::make(__('Post types with hidden author name'), 'post_types_with_hidden_author_name')
                    ->options(PostTypes::all())
                    ->nullable()
                    ->onlyOnForms(),

                Boolean::make(__('Hide author page'), 'is_author_page_hidden')
                    ->onlyOnForms()
                    ->sortable()
                    ->help(__('Does not affect users who have only the columnist role.')),

                Boolean::make(__('Hide columnist page'), 'is_columnist_page_hidden')
                    ->onlyOnForms()
                    ->sortable()
                    ->help(__('Does not affect users who do not have the columnist role.')),
            ]),

            Panel::make(__('User Information'), [
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

    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->where('language_code', app()->getLocale())
            ->whereJsonContains('allowed_post_types', [$request->newResource()->getPostType()]);
    }
}
