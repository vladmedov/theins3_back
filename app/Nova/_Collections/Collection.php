<?php

namespace App\Nova\_Collections;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Badge;

use Outl1ne\NovaSortable\Traits\HasSortableRows;

use Medov\DateTimeSplit\DateTimeSplit;

class Collection extends Resource
{
    use HasSortableRows {
        indexQuery as indexSortableQuery;
    }

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\CollectionPost>
     */
    public static $model = \App\Models\CollectionPost::class;

    public static $clickAction = 'edit';

    public static $globallySearchable = false;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    protected static function filterPostType(): string {
        return false;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Hidden::make(__('Language code'), 'language_code')->default(app()->getLocale()),
            Hidden::make('collection_code')->default(static::getCollectionType()),

            Badge::make(__('Status'), function () {
                return $this->post->status;
            })->map([
                'draft' => 'danger',
                'published' => 'success',
            ])->label(function ($value) {
                return __($value);
            }),

            DateTimeSplit::make(__('Publication date'), function () {
                return $this->post->published_at;
            }),
            
            BelongsTo::make(__('Title'), 'post', PostCollection::class)
                ->searchable()
                ->withSubtitles()
                ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                    if ($postType = static::filterPostType()) {
                        $query->where('type', $postType);
                    }

                    $query
                        ->where('language_code', app()->getLocale())
                        ->whereNotIn('id', function ($subquery) {
                            $subquery
                                ->select('post_id')
                                ->from('collection_post')
                                ->where('collection_code', static::getCollectionType());
                        });
                }),

            Text::make(__('Category'), function () {
                return $this->post->category->title;
            }),

            Text::make(__('Author'), function () {
                $authors = $this->post->authors();
                if ($authors->count() > 0) {
                    return $authors->first()->fullname;
                } else {
                    return '<span style="color:#ccc">The Insider</span>';
                }
                
            })->asHtml(),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    public function authorizedToView(Request $request)
    {
        return false;
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
        $query
            ->where('language_code', app()->getLocale())
            ->where('collection_code', static::getCollectionType());
        return parent::indexQuery($request, static::indexSortableQuery($request, $query));
    }

    protected static function getCollectionType(): string {
        return 'feature';
    }
}
