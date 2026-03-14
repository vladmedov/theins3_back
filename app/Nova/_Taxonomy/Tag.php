<?php

namespace App\Nova\_Taxonomy;

use App\Support\Nova\FormActionBar;
use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Panel;

class Tag extends Resource
{
    public static $model = \App\Models\Tag::class;

    public static $title = 'title';
    public static $search = ['id', 'title', 'slug'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        $actionBarHtml = FormActionBar::render([
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
        ]);
        $generalFields = [
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

        return [
            Hidden::make(__('Language code'), 'language_code')
                ->default(app()->getLocale()),

            Heading::make($actionBarHtml)
                ->onlyOnForms()
                ->asHtml(),

            Panel::make(__('General'), $generalFields),
        ];
    }

    public static function label() {
        return __('Tags');
    }
    
    public static function singularLabel() {
        return __('Tag');
    }

    public static function redirectAfterCreate(NovaRequest $request, Resource $resource)
    {
        return '/resources/' . static::uriKey() . '/' . $resource->getKey() . '/edit';
    }

    public static function redirectAfterUpdate(NovaRequest $request, Resource $resource)
    {
        return '/resources/' . static::uriKey() . '/' . $resource->getKey() . '/edit';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->where('language_code', app()->getLocale());
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
