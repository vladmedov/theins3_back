<?php

namespace App\Nova\_Taxonomy;

use App\Support\Nova\FormActionBar;
use App\Support\Nova\PageTitle;
use App\Support\Nova\PanelWithoutHeader;
use App\Nova\Resource;

use Illuminate\Http\Request;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Panel;

use Outl1ne\NovaSortable\Traits\HasSortableRows;

use \App\Enums\PostTypes;
use \App\Enums\CategoryTypes;

class Category extends Resource
{
    use HasSortableRows {
        indexQuery as indexSortableQuery;
    }

    public static $model = \App\Models\Category::class;

    public static $title = 'title';
    public static $search = ['id', 'title', 'slug'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        $locale = $this->effectiveResourceLanguageCode();

        $generalFields = [
            Boolean::make(__('Is show in the menu?'), 'is_show_in_menu')
                ->default(true)
                ->sortable(),
 
            Text::make(__('Title'), 'title')
                ->sortable()
                ->rules('required', 'max:255'),
    
            Text::make(__('Slug'), 'slug')
                ->sortable()
                ->rules('required', 'max:255'),
    
            Select::make(__('Category type'), 'type')
                ->options(CategoryTypes::all())
                ->default(CategoryTypes::DEFAULT)
                ->sortable()
                ->rules('required'),

            Text::make(__('Posts count'), function () {
                return $this->posts()->count();
            }),
        ];

        $pageTitleRow = PageTitle::make($this, static::uriKey().'EditTitleRow', [
            Hidden::make(__('Language'), 'language_code')
                ->default($locale),
        ]);

        $formActionBar = PanelWithoutHeader::make([
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
            ], '_form_action_bar_top'),
        ], 'FormActionBarTop');

        return [
            $pageTitleRow,
            $formActionBar,

            Panel::make(__('General'), $generalFields),
        ];
    }

    public static function label() {
        return __('Categories');
    }
    
    public static function singularLabel() {
        return __('Category');
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/' . static::uriKey() . '/' . $resource->getKey() . '/edit';
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/' . static::uriKey() . '/' . $resource->getKey() . '/edit';
    }

    public static function indexQuery(NovaRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query): \Illuminate\Contracts\Database\Eloquent\Builder
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
