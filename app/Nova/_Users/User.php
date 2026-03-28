<?php

namespace App\Nova\_Users;

use App\Support\Nova\FormActionBar;
use App\Support\Nova\PageTitle;
use App\Support\Nova\PanelWithoutHeader;
use App\Support\Nova\RelatedPostsPanel;
use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Badge;
use Medov\DateTimeSplit\DateTimeSplit;

use App\Enums\UserRoles;

use App\Nova\_Posts\PostArticle;
use App\Nova\_Posts\PostNews;
use App\Nova\_Posts\PostOpinion;
use App\Nova\_Posts\PostOnline;

//use Outl1ne\NovaSortable\Traits\HasSortableManyToManyRows;

class User extends Resource
{
    // use HasSortableManyToManyRows;
    // public static $sortableCacheEnabled = false;

    // public $sortable = [
    //     'only_sort_on' => \App\Nova\_Posts\Post::class,
    // ];

    public static $model = \App\Models\User::class;

    public static $title = 'name';
    public static $search = ['id', 'email', 'name'];

    public static $clickAction = 'edit';
    
    public function fields(NovaRequest $request) {
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

        $userEditBaseUrl = $this->resource?->exists
            ? '/admin/resources/' . static::uriKey() . '/' . $this->resource->getKey() . '/edit'
            : null;
        $buildUserEditPageUrl = function (int $page) use ($userEditBaseUrl, $request) {
            if (!$userEditBaseUrl) {
                return null;
            }

            $query = array_filter([
                'viaResource' => $request->query('viaResource'),
                'viaResourceId' => $request->query('viaResourceId'),
                'viaRelationship' => $request->query('viaRelationship'),
                'relationshipType' => $request->query('relationshipType'),
                'related_posts_page' => $page,
            ], fn ($value) => $value !== null && $value !== '');

            return $userEditBaseUrl . (!empty($query) ? '?' . http_build_query($query) : '');
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
        $relatedPostsHtml = RelatedPostsPanel::render($relatedPostsPaginator, $buildUserEditPageUrl, [
            'showHeader' => false,
            'withOuterCard' => false,
        ]);
        $generalFields = [
            ID::make()->onlyOnDetail(),

            Text::make(__('Name (RU)'), 'name')
                ->rules('required', 'max:255'),

            Email::make(__('Email'), 'email')
                ->sortable(),
        
            Password::make(__('Password'), 'password')
                ->onlyOnForms(),

            Select::make(__('Role'), 'role_code')
                ->options(UserRoles::all())
                ->rules('required'),

            BooleanGroup::make(__('Available languages'), 'available_languages')
                ->onlyOnForms()
                ->options([
                    'ru' => __('Russian'),
                    'en' => __('English')   
                ])
                ->rules('required', function($attribute, $value, $fail) {
                    $decodedValue = json_decode($value, true);
                    if (!is_array($decodedValue) || !array_filter($decodedValue)) {
                        $fail(__('Choose at least one language.'));
                    }
                })
                ->help(__('Choose at least one language.')),

            Select::make(__('Timezone'), 'timezone')
                ->options(function () {
                    $allTimezones = timezone_identifiers_list();
                    $timezones = array_combine($allTimezones, $allTimezones);
                    
                    $priorityTimezones = [
                        'Europe/Moscow' => 'Europe/Moscow (MSK)',
                        'Europe/Warsaw' => 'Europe/Warsaw (CET)',
                        'Europe/Riga' => 'Europe/Riga (EEST)',
                        'UTC' => 'UTC'
                    ];
                    
                    foreach (array_keys($priorityTimezones) as $tz) {
                        if (isset($timezones[$tz])) {
                            unset($timezones[$tz]);
                        }
                    }
                    
                    return $priorityTimezones + $timezones;
                })
                ->searchable()
                ->rules('required')
                ->default('Europe/Moscow')
                ->help(__('Select your local timezone')),
        ];

        $pageTitleRow = PageTitle::make($this, static::uriKey().'EditTitleRow', [], function ($r) {
            if (! $r->exists) {
                return __('Publication form new');
            }
            $headline = trim((string) ($r->resource->name ?? ''));

            return $headline !== '' ? $headline : __('Publication form no title');
        });

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

        return array_merge([
            $pageTitleRow,
            $formActionBar,

            Panel::make(__('General'), $generalFields),

            Text::make(__('Posts count'), 'posts_count')
                ->onlyOnIndex()
                ->sortable(),

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

            DateTimeSplit::make(__('Created'), 'created_at')->onlyOnDetail(),
            DateTimeSplit::make(__('Updated'), 'updated_at')->onlyOnDetail(),
                
        ]
       );
        
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
        if (static::getUserRole()) {
            $query->where('role_code', static::getUserRole());
        }

        $query->withCount('posts');

        return $query;
    }

    public function subtitle() {
        return trim("{$this->name} ({$this->email})");
    }

    public static function label() {
        return __('All users');
    }
    
    public static function singularLabel() {
        return __('User');
    }

    public function getPostsCountTitle() {
        return __('Posts count');
    }

    public function getPostsCount() {
        return $this->posts()->count();
    }

    public static function createButtonLabel(): string
    {
        return __('Create');
    }

    public static function updateButtonLabel(): string
    {
        return __('Save');
    }

    protected static function getUserRole() {
        return false;
    }

    protected static function getPostType() {
        return false;
    }
}
