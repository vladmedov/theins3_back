<?php

namespace App\Nova\_Taxonomy;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Select;

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
        return [
            Hidden::make(__('Language'), 'language_code')
                ->default(app()->getLocale()),
    
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
    }

    public static function label() {
        return __('Categories');
    }
    
    public static function singularLabel() {
        return __('Category');
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey();
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey();
    }

    public static function indexQuery(NovaRequest $request, $query) {
        $query->where('language_code', app()->getLocale());
        return parent::indexQuery($request, static::indexSortableQuery($request, $query));
    }
}
