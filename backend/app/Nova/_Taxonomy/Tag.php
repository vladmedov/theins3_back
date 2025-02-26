<?php

namespace App\Nova\_Taxonomy;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Hidden;

class Tag extends Resource
{
    public static $model = \App\Models\Tag::class;

    public static $title = 'title';
    public static $search = ['id', 'title', 'slug'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        return [
            Hidden::make(__('Language code'), 'language_code')
                ->default(app()->getLocale()),

            Text::make(__('Title'), 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Slug::make('Slug', 'slug')
                ->from('title')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('Posts count'), function () {
                return $this->posts()->count();
            }),
        ];
    }

    public static function label() {
        return __('Tags');
    }
    
    public static function singularLabel() {
        return __('Tag');
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey();
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey();
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->where('language_code', app()->getLocale());
    }
}
