<?php

namespace App\Nova\_Taxonomy;

use App\Support\Nova\FormActionBar;
use App\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Illuminate\Support\Facades\Storage;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Panel;

use App\Services\ImageService;
use Outl1ne\NovaSortable\Traits\HasSortableRows;

class InvestigationTheme extends Resource
{
    use HasSortableRows {
        indexQuery as indexSortableQuery;
    }

    public static $model = \App\Models\InvestigationTheme::class;
    
    public static $title = 'title';
    public static $search = ['id', 'title', 'slug'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        $locale = $this->effectiveResourceLanguageCode();

        $generalFields = [
            Boolean::make(__('Is it main Insvestigation theme?'), 'is_main')
                ->sortable()
                ->rules('boolean')
                ->help(__('Check if this Investigation Topic should be the main one. If you already have a main Investigation Topic, it will be reset.')),

            Text::make(__('Title'), 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Textarea::make(__('Description'), 'description')
                ->onlyOnForms(),

            Image::make(__('Image cover'), 'cover_image')
                ->hideFromIndex()
                ->disk('public')
                ->rules('nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120')
                ->nullable()
                ->path(ImageService::getImagePath($this->id, ImageService::TYPE_THEME_COVER, ImageService::SIZE_ORIGINAL))
                ->preview(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                })
                ->thumbnail(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                }),

            Slug::make('Slug', 'slug')
                ->onlyOnForms()
                ->from('title')
                ->sortable()
                ->rules('required', 'max:255'),

            Number::make(__('Position'), 'position')
                ->onlyOnForms()
                ->sortable(),

            Text::make(__('Posts count'), function () {
                return $this->posts()->count();
            }),
        ];

        return [
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

            Panel::make(__('General'), $generalFields),
        ];
    }

    public static function label() {
        return __('Investigation themes');
    }
    
    public static function singularLabel() {
        return __('Investigation theme');
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/' . static::uriKey() . '/' . $resource->getKey() . '/edit';
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/' . static::uriKey() . '/' . $resource->getKey() . '/edit';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        $query->where('language_code', static::resolveResourceLanguageCodeForRequest($request));
        return parent::indexQuery($request, static::indexSortableQuery($request, $query));
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
