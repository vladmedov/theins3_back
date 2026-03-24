<?php

namespace App\Nova\_Taxonomy;

use App\Support\Nova\FormActionBar;
use App\Support\Nova\RelatedPostsPanel;
use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;

use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

use Mostafaznv\NovaCkEditor\CkEditor;

class Termin extends Resource
{
    public static $model = \App\Models\Termin::class;

    public static $title = 'termin';
    public static $search = ['id', 'termin', 'description'];

    public static $clickAction = 'edit';

    public function fields(Request $request) {
        $relatedPostsPageFromReferer = null;
        $refererUrl = $request->headers->get('referer');
        if ($refererUrl) {
            $refererQuery = parse_url($refererUrl, PHP_URL_QUERY);
            if ($refererQuery) {
                parse_str($refererQuery, $refererQueryParams);
                $relatedPostsPageFromReferer = isset($refererQueryParams['related_posts_page'])
                    ? (int) $refererQueryParams['related_posts_page']
                    : null;
            }
        }

        $terminEditBaseUrl = $this->resource?->exists
            ? '/admin/resources/' . static::uriKey() . '/' . $this->resource->getKey() . '/edit'
            : null;
        $buildTerminEditPageUrl = function (int $page) use ($terminEditBaseUrl, $request) {
            if (!$terminEditBaseUrl) {
                return null;
            }

            $query = array_filter([
                'viaResource' => $request->query('viaResource'),
                'viaResourceId' => $request->query('viaResourceId'),
                'viaRelationship' => $request->query('viaRelationship'),
                'relationshipType' => $request->query('relationshipType'),
                'related_posts_page' => $page,
            ], fn ($value) => $value !== null && $value !== '');

            return $terminEditBaseUrl . (!empty($query) ? '?' . http_build_query($query) : '');
        };
        $relatedPostsPage = max(
            1,
            (int) ($request->get('related_posts_page')
                ?? $relatedPostsPageFromReferer
                ?? 1)
        );
        $relatedPostsPaginator = $this->resource?->exists
            ? $this->resource->posts()
                ->select('posts.id', 'posts.title', 'posts.type', 'posts.published_at')
                ->orderByDesc('published_at')
                ->paginate(10, ['*'], 'related_posts_page', $relatedPostsPage)
            : null;
        $postsCount = $relatedPostsPaginator?->total() ?? 0;
        $warningHtml = sprintf(
            '<div style="padding:12px 16px;border:1px solid #f59e0b;border-radius:8px;background:#fffbeb;color:#92400e;line-height:1.5;">%s</div>',
            e(__('termin.warning'))
        );
        $relatedPostsHtml = RelatedPostsPanel::render($relatedPostsPaginator, $buildTerminEditPageUrl, [
            'showHeader' => false,
            'withOuterCard' => false,
        ]);
        $formFields = [
            Heading::make($warningHtml)
                ->onlyOnForms()
                ->asHtml()
                ->canSee(fn ($request) => !empty($request->resourceId)),

            Text::make(__('Term'), 'termin')
                ->sortable()
                ->rules('required', 'max:255'),

            CkEditor::make(__('Description'), 'description')
                ->toolbar('toolbar-theins-mini')
                ->hideFromIndex()
                ->rules('required'),

            Text::make(__('Posts count'), function () {
                return $this->posts()->count();
            }),
        ];

        return [
            Hidden::make(__('Language code'), 'language_code')
                ->default(app()->getLocale()),

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

            Panel::make(__('General'), $formFields),

            ...(
                !empty($request->resourceId) && $postsCount > 0
                    ? [
                        Panel::make(__('related_posts_panel.heading'), [
                            Heading::make($relatedPostsHtml)
                                ->onlyOnForms()
                                ->asHtml(),
                        ]),
                    ]
                    : []
            ),
        ];
    }

    public static function label() {
        return __('Terms');
    }
    
    public static function singularLabel() {
        return __('Term');
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
