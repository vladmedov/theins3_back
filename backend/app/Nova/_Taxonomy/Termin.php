<?php

namespace App\Nova\_Taxonomy;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;

use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Markdown;

class Termin extends Resource
{
    public static $model = \App\Models\Termin::class;

    public static $title = 'termin';
    public static $search = ['id', 'termin', 'description'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        return [
            Hidden::make(__('Language code'), 'language_code')
                ->default(app()->getLocale()),

            Text::make(__('Termin'), 'termin')
                ->sortable()
                ->rules('required', 'max:255'),

            Markdown::make(__('Description'), 'description')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('Posts count'), function () {
                return $this->posts()->count();
            }),
        ];
    }

    public static function label() {
        return __('Termins');
    }
    
    public static function singularLabel() {
        return __('Termin');
    }

    public static function redirectAfterCreate(NovaRequest $request, Resource $resource)
    {
        return '/resources/'.static::uriKey();
    }

    public static function redirectAfterUpdate(NovaRequest $request, Resource $resource)
    {
        return '/resources/'.static::uriKey();
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->where('language_code', app()->getLocale());
    }
}
