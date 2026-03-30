<?php

namespace App\Nova\_Posts;

use App\Enums\CategoryTypes;
use App\Enums\PostTypes;
use App\Nova\_Collections\PostCollection;
use App\Nova\_Taxonomy\Author;
use App\Nova\_Taxonomy\Category;
use App\Nova\_Taxonomy\InvestigationTheme;
use App\Nova\_Taxonomy\Tag;
use App\Nova\_Users\User;
use App\Nova\Fields\ImageCropperDnd as ImageCropper;
use App\Nova\Metrics\PostsPerDay;
use App\Nova\Resource;
use App\Services\ImageService;
use App\Services\Nova\PostEditLockService;
use App\Services\PostPreviewTokenService;
use App\Support\Nova\FormActionBar;
use App\Support\Nova\PageTitle;
use App\Support\Nova\PanelWithoutHeader;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
// use Spatie\MediaLibrary\MediaCollections\Models\Media;
// use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
// use Ebess\AdvancedNovaMediaLibrary\Fields\Images;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Tag as TagField;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Http\Requests\UpdateResourceRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Panel;
use Laravel\Nova\Resource as NovaResource;
use Laravel\Nova\Tabs\Tab;
use Medov\DateTimeSplit\DateTimeSplit;
use Medov\ImageGallery\ImageGallery;
use Medov\InsertionCode\InsertionCode;
use Medov\PostHistory\PostHistory;
use Mostafaznv\NovaCkEditor\CkEditor;
use Outl1ne\MultiselectField\Multiselect as EntityMultiselect;
use Whitecube\NovaFlexibleContent\Flexible;

// use Outl1ne\NovaSortable\Traits\HasSortableManyToManyRows;

abstract class Post extends Resource
{
    // use HasSortableManyToManyRows;
    // public static $sortableCacheEnabled = false;

    // public $sortable = [
    //     'only_sort_on' => \App\Nova\_Users\User::class,
    // ];

    public static $title = 'title';

    public static $search = ['id', 'title'];

    public static $clickAction = 'edit';

    public static function getPostType()
    {
        if (defined(static::$model.'::TYPE')) {
            return constant(static::$model.'::TYPE');
        }

        return null;
    }

    /**
     * First author row linked to the user for the current locale and this resource’s post type
     * (same filters as the Authors multiselect options / Author::relatableQuery).
     */
    protected static function firstLinkedAuthorForCurrentUser(?\Illuminate\Contracts\Auth\Authenticatable $user, string $languageCode): ?\App\Models\Author
    {
        if ($user === null) {
            return null;
        }
        $postType = static::getPostType();
        if ($postType === null) {
            return null;
        }

        return \App\Models\Author::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('language_code', $languageCode)
            ->whereJsonContains('allowed_post_types', [$postType])
            ->orderBy('id')
            ->first();
    }

    public function filters(Request $request)
    {
        $filters = [];
        $postType = static::getPostType();

        if ($postType && CategoryTypes::isDefault($postType)) {
            $filters[] = new \App\Nova\Filters\CategoryFilter;
        }

        $filters[] = new \App\Nova\Filters\AuthorFilter;
        $filters[] = new \App\Nova\Filters\StatusFilter;
        $filters[] = new \App\Nova\Filters\DatePublishedFromFilter;
        $filters[] = new \App\Nova\Filters\DatePublishedToFilter;

        return $filters;
    }

    public function cards(NovaRequest $request): array
    {
        return [
            PostsPerDay::make(static::getPostType())
                ->width('full')
                ->refreshWhenFiltersChange(),
        ];
    }

    /**
     * Only persisted updates (Nova ResourceUpdateController) require the Redis edit lock; opening the form does not.
     */
    public function authorizeToUpdate(Request $request): void
    {
        parent::authorizeToUpdate($request);

        if ($request instanceof UpdateResourceRequest) {
            $postKey = PostEditLockService::makePostKey(static::uriKey(), (string) $this->resource->getKey());
            app(PostEditLockService::class)->assertCanEditOrFail($postKey, $request->user());
        }
    }

    public function fields(Request $request)
    {
        $locale = $this->effectiveResourceLanguageCode();

        $postUrl = ($this->exists && $this->category)
            ? rtrim(config('app.preview_url', config('app.frontend_url', config('app.url'))), '/').$this->getPath()
            : null;

        $isDraft = $this->exists && $this->status === 'draft';
        if ($postUrl && $isDraft) {
            $token = app(PostPreviewTokenService::class)->createToken($this->resource);
            $postUrl .= (str_contains($postUrl, '?') ? '&' : '?').'preview='.$token;
        }

        $previewNotice = '';
        $previewNoticeExpiresAt = null;
        if ($isDraft && $postUrl) {
            $expiresAt = Carbon::now()->addMinutes(PostPreviewTokenService::TTL_MINUTES);
            $previewNoticeExpiresAt = $expiresAt->copy()->toIso8601String();
            $previewNotice = __('Preview valid until').' ...';
            $previewNotice .= ' · '.__('To refresh the token, reload the page.');
        }

        // Form Action Bars
        $infoBarOptions = [
            'stay' => [
                'exists' => $this->exists,
                'status' => $this->status,
            ],
            'toggle_publish' => [
                'status' => $this->status,
            ],
            'url' => $postUrl ? [
                'url' => $postUrl,
                'notice' => $previewNotice ?: null,
                'noticeExpiresAt' => $previewNoticeExpiresAt,
                'noticePrefix' => __('Preview valid until'),
                'noticeSuffix' => __('To refresh the token, reload the page.'),
            ] : null,
            'autosave' => $this->exists ? [
                'enabled' => $isDraft,
                'updated_at' => $this->updated_at,
            ] : null,
        ];
        $infoBarTopOptions = array_merge($infoBarOptions, [
            'scrollNav' => [
                'direction' => 'down',
                'title' => __('Scroll to bottom'),
            ],
        ]);
        $infoBarBottomOptions = array_merge($infoBarOptions, [
            'scrollNav' => [
                'direction' => 'up',
                'title' => __('Scroll to top'),
            ],
        ]);

        // Только экран редактирования: иначе Nova вызывает fields() на каждую строку индекса/другие API —
        // claimIfMissing создавал бы ключи в Redis для всех видимых публикаций.
        $postEditLock = null;
        if ($request instanceof NovaRequest && $request->isUpdateOrUpdateAttachedRequest()) {
            $postEditLock = $this->buildPostEditLockFormOptions($request, $postUrl);
        }
        if ($postEditLock !== null) {
            $infoBarTopOptions = array_merge($infoBarTopOptions, [
                'postEditLockLockHtml' => $postEditLock['lockContentHtml'],
                'initialCanEdit' => $postEditLock['initialCanEdit'],
                'postEditLockMeta' => $postEditLock['meta'],
                'postEditLockEnabled' => true,
            ]);
            $infoBarBottomOptions = array_merge($infoBarBottomOptions, [
                'postEditLockLockHtml' => $postEditLock['lockContentHtml'],
                'initialCanEdit' => $postEditLock['initialCanEdit'],
                'postEditLockEnabled' => true,
                'postEditLockMeta' => $postEditLock['meta'],
            ]);
        }

        $FormActionBarTop = PanelWithoutHeader::make([
            FormActionBar::make($infoBarTopOptions, '_form_action_bar_top'),
        ], 'FormActionBarTop');

        $FormActionBarBottom = PanelWithoutHeader::make([
            FormActionBar::make($infoBarBottomOptions, '_form_action_bar_bottom'),
        ], 'FormActionBarBottom');

        // Tab 1
        $general = [
            Text::make(__('Type'), 'type')
                ->onlyOnDetail()
                ->default(__(static::getPostType())),

            Select::make(__('Status'), 'status')
                ->options([
                    'draft' => __('Draft'),
                    'published' => __('Published'),
                ])
                ->default('draft')
                ->displayUsingLabels()
                ->onlyOnForms(),

            DateTimeSplit::make(__('Publication date'), 'published_at')
                ->onlyOnForms()
                ->default(now())
                ->help(__('Time is shown in your device timezone.'))
                ->rules('required'),

            Text::make(__('Date/time'), 'published_at')
                ->exceptOnForms()
                ->sortable()
                ->rules('required', 'max:255')
                ->displayUsing(function ($date, $resource) use ($request) {
                    $url = config('nova.path') . static::redirectAfterUpdate($request, $this);
                    $formatted = static::formatPublishedAtForUser($date);

                    if (!$formatted) {
                        return '';
                    }

                    [$d, $t] = explode(' ', $formatted, 2) + [null, null];
                    $today = now()->format('d.m.Y');
                    $dateHtml = $d === $today
                        ? "<span class=\"nova-post-index-time font-bold\">{$t}</span>"
                        : "<span class=\"nova-post-index-date font-bold\">{$d}</span>" .
                          ($t ? "<br><span class=\"nova-post-index-time\">{$t}</span>" : '');

                    $classes = match (true) {
                        $resource->status == 'published' => 'nova-post-index-date-link nova-post-index-date-published shrink-0',
                        !empty($resource->auto_publish_pending) => 'nova-post-index-date-link nova-post-index-date-draft-auto shrink-0',
                        default => 'nova-post-index-date-link nova-post-index-date-draft nova-post-index-draft shrink-0'
                    };

                    $dateHtml = "<a href=\"{$url}\" class=\"{$classes}\">{$dateHtml}</a>";

                    $autoQueue = !empty($resource->auto_publish_pending)
                        ? '<span class="nova-post-auto-queue ml-1 inline-flex h-4 w-4 shrink-0 items-center justify-center self-center rounded-full text-[10px] font-semibold leading-none" title="' .
                            e(__('Auto-queue')) . '">' . e(__('Auto-queue marker')) . '</span>' : '';

                    return '<span class="inline-flex max-w-full flex-nowrap items-center gap-1 whitespace-nowrap">' . $dateHtml . $autoQueue . '</span>';
                })
                ->asHtml(),

            Text::make(__('Title'), 'title')
                ->rules('required', 'max:140')
                ->help(__('Autosave is enabled for this field.'))
                ->withMeta(['extraAttributes' => [
                    'data-char-counter' => 'title',
                    'maxlength' => '140',
                    'data-post-autosave-field' => '1',
                ]])
                ->displayUsing(function ($title, $resource) use ($request) {
                    $url = config('nova.path').static::redirectAfterUpdate($request, $this);

                    return $resource->is_super_news
                        ? "<a href='{$url}'><div class='nova_view_post_title font-bold'>{$title}</div>"
                        : "<a href='{$url}'><div class='nova_view_post_title'>{$title}</div></a>";
                })
                ->asHtml(),

            \App\Models\Category::where('type', CategoryTypes::getCategoryTypeByPostType(static::getPostType()))
                ->where('language_code', $locale)
                ->count() === 1
                    ? Hidden::make(__('Category'), 'category_id')
                        ->default(function () use ($locale) {
                            $category = \App\Models\Category::where('type', CategoryTypes::getCategoryTypeByPostType(static::getPostType()))
                                ->where('language_code', $locale)
                                ->first();

                            return $category?->id;
                        })
                    : BelongsTo::make(__('Category'), 'category', Category::class)
                        ->relatableQueryUsing(function (Request $request, Builder $query) use ($locale) {
                            $query
                                ->where('type', CategoryTypes::getCategoryTypeByPostType(static::getPostType()))
                                ->where('language_code', $locale);
                        }),

            static::getPostType() == PostTypes::OPINION
                ? BelongsTo::make(__('Columnist'), 'columnist', Author::class)
                    ->hideFromDetail()
                    ->searchable()
                    ->withSubtitles()
                    ->display(function ($authorResource) {
                        $request = app(NovaRequest::class);
                        if ($request->isResourceIndexRequest()) {
                            $name = trim((string) ($authorResource->resource->full_name ?? ''));

                            return $name !== '' ? $name : $authorResource->title();
                        }

                        return $authorResource->title();
                    })
                    ->default(function (NovaRequest $request) use ($locale) {
                        $author = static::firstLinkedAuthorForCurrentUser($request->user(), $locale);

                        return $author?->id;
                    })
                    // ->immutable(function ($request) {
                    //     return !$request->user()->canViewAll();
                    // })
                : EntityMultiselect::make(__('Authors'), 'authors')
                    ->onlyOnForms()
                    ->belongsToMany(Author::class, false)
                    //->options(\App\Models\Author::getAuthorsByPostType($locale, [$this->type]))
                    ->reorderable()
                    ->optionsLimit(5)
                    ->default(function (NovaRequest $request) use ($locale) {
                        $author = static::firstLinkedAuthorForCurrentUser($request->user(), $locale);

                        return $author !== null ? collect([$author]) : null;
                    })
                    ->when($this->exists, function ($field) {
                        return $field->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                            $authors = $request->{$requestAttribute} ?? [];
                            $syncData = [];
                            foreach ($authors as $index => $authorId) {
                                $syncData[$authorId] = ['position' => $index + 1];
                            }
                            $model->authors()->sync($syncData);
                        });
                    }),

            ImageCropper::make(__('Image file'), 'image')
                ->hideFromDetail()
                ->hideFromIndex()
                ->disk('public')
                ->croppable(3 / 2)
                ->withMeta([
                    'acceptedTypes' => '.jpeg,.jpg,.png,.webp',
                ])
                ->rules('image', 'mimes:jpeg,png,jpg,webp', 'max:20480', 'dimensions:min_width=640,min_height=100')
                ->help(__('Allowed formats: jpeg, jpg, png, webp. Max size: 20 MB. Minimum dimensions: 640x100 px.'))
                ->dependsOn(
                    ['ignore_image_dimension_requirements'],
                    function (ImageCropper $field, NovaRequest $request, FormData $formData) {
                        if ($formData->ignore_image_dimension_requirements) {
                            $field->rules('image', 'mimes:jpeg,png,jpg,webp', 'max:20480')
                                ->help(__('Allowed formats: jpeg, jpg, png, webp. Max size: 20 MB. Minimum dimensions are ignored.'));
                        } else {
                            $field->rules('image', 'mimes:jpeg,png,jpg,webp', 'max:20480', 'dimensions:min_width=640,min_height=100')
                                ->help(__('Allowed formats: jpeg, jpg, png, webp. Max size: 20 MB. Minimum dimensions: 640x100 px.'));
                        }
                    }
                )
                ->nullable()
                ->path(ImageService::getImagePath($this->id, ImageService::TYPE_POST_COVER, ImageService::SIZE_ORIGINAL))
                ->preview(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                })
                ->thumbnail(function ($value, $disk) {
                    return $value ? Storage::disk($disk)->url($value) : null;
                }),

            Text::make(__('Image description'), 'image_description')
                ->hideFromDetail()
                ->hideFromIndex()
                ->sortable()
                ->rules('max:255')
                ->help(__('Autosave is enabled for this field.'))
                ->withMeta(['extraAttributes' => ['data-post-autosave-field' => '1']]),

            // Textarea::make(__('Lead text'), 'lead')
            //     ->hideFromDetail()
            //     ->rules('nullable', 'max:500'),

            CkEditor::make(__('Lead text'), 'lead')
                ->toolbar('toolbar-theins-small')
                ->hideFromIndex()
                ->hideFromDetail()
                ->rules('nullable')
                ->help(__('Autosave is enabled for this field.'))
                ->withMeta(['extraAttributes' => ['data-post-autosave-field' => '1']]),
        ];

        if (static::getPostType() == PostTypes::NEWS) {
            $general[] = Boolean::make(__('Super news'), 'is_super_news')
                ->onlyOnForms()
                ->sortable()
                ->rules('boolean')
                ->help(__('posts.super_news_help'));
        }

        $general[] = Boolean::make(__('Ignore image dimension requirements'), 'ignore_image_dimension_requirements')
            ->onlyOnForms()
            ->help('<strong>'.__('Use this option only if no high-quality publication image is available. Low-quality images reduce the overall quality of the site.').'</strong><br>'.__('If enabled, minimum image dimensions (640x100) will not be validated.'))
            ->withMeta([
                'extraAttributes' => [
                    'class' => 'ignore-image-dimensions-boolean',
                ],
            ])
            ->fillUsing(function () {
                // UI-only toggle, do not persist to the model.
            });

        $general[] = Boolean::make(__('Queued for auto-publication'), 'auto_publish_pending')
            ->sortable()
            ->hideFromIndex()
            ->hideFromDetail()
            ->help(__('When the publication time arrives, status becomes Published and this option is cleared automatically.'));

        // Tab 2
        $content = [
            Flexible::make('', 'content')
                ->hideFromDetail()
                ->menu('custom-flexible-menu')
                ->fullWidth()
                ->withMeta(['extraAttributes' => ['data-post-autosave-content' => '1']])

                ->addLayout(__('Text'), 'text', [
                    CkEditor::make(__('Text'), 'text')
                        ->fullWidth()
                        ->stacked(),
                ])

                ->addLayout(__('Images'), 'images', [
                    InsertionCode::make(__('insertion_code.label'), 'show_insertion_code')
                        ->forLayout('images')
                        ->default(true),
                    ImageGallery::make(__('Image list'), 'images')
                        ->fullWidth()
                        ->stacked()
                        ->rules('nullable'),
                ])
                ->addLayout(__('Video'), 'video', [
                    InsertionCode::make(__('insertion_code.label'), 'show_insertion_code')
                        ->forLayout('video')
                        ->default(true),
                    Text::make(__('Video URL'), 'video_url')
                        ->fullWidth()
                        ->stacked()
                        ->rules('url'),

                    Text::make(__('Description'), 'video_description')
                        ->fullWidth()
                        ->stacked(),

                    Text::make(__('Author'), 'video_author')
                        ->fullWidth()
                        ->stacked(),
                ])
                ->addLayout(__('IFrame / Embed'), 'embed', [
                    InsertionCode::make(__('insertion_code.label'), 'show_insertion_code')
                        ->forLayout('embed')
                        ->default(true),
                    Textarea::make(__('Embed code'), 'embed_code')
                        ->fullWidth()
                        ->rules('required')
                        ->stacked(),

                    Select::make(__('Embed type'), 'embed_type')
                        ->options([
                            'hidden' => __('Hidden'),
                            'telegram' => 'Telegram',
                            'twitter' => 'Twitter',
                            'facebook' => 'Facebook',
                            'instagram' => 'Instagram',
                            'vk' => 'VK',
                            'iframe' => 'iFrame',
                        ])
                        ->default('hidden')
                        ->displayUsingLabels()
                        ->rules('required')
                        ->stacked(),
                ])

                ->addLayout(__('Quote'), 'quote', [
                    InsertionCode::make(__('insertion_code.label'), 'show_insertion_code')
                        ->forLayout('quote')
                        ->default(true),
                    Textarea::make(__('Quote'), 'quote')
                        ->fullWidth()
                        ->stacked(),
                    Text::make(__('Quote author'), 'quote_author')
                        ->fullWidth()
                        ->stacked()
                        ->nullable(),
                ])

                ->addLayout(__('Related posts'), 'related', [
                    InsertionCode::make(__('insertion_code.label'), 'show_insertion_code')
                        ->forLayout('related')
                        ->default(false),

                    Text::make(__('Related posts title'), 'related_title')
                        ->fullWidth()
                        ->stacked(),

                    EntityMultiselect::make(__('Related posts'), 'related_posts')
                        ->fullWidth()
                        ->stacked()
                        ->saveAsJSON()
                        ->reorderable()
                        ->asyncResource(PostRelated::class),
                ]),
        ];

        // Tab 3
        $settings = [
            Heading::make('<h3 style="margin-top: 0px;" class="uppercase tracking-wide font-bold text-s">'.__('General').'</h3>')
                ->hideFromDetail()
                ->asHtml(),

            Select::make(__('Author Visibility'), 'author_visibility')
                ->onlyOnForms()
                ->fullWidth()
                ->options([
                    'default' => __('Author settings'),
                    'force_hidden' => __('Force Hidden'),
                    'force_shown' => __('Force Shown'),
                ])
                ->default('default')
                ->displayUsingLabels()
                ->help(__('Controls the visibility of authors. Overrides default settings.')),

            Text::make(__('Authors'),
                function () {
                    if ($this->authors->isNotEmpty()) {
                        return $this->authors->pluck('fullname')->implode('<br>');
                    } else {
                        return '<span style="color:#ccc">The Insider</span>';
                    }
                })
                ->asHtml()
                ->hideFromDetail()
                ->hideFromIndex(function () {
                    return static::getPostType() == PostTypes::OPINION;
                })
                ->displayUsing(function ($value, $resource) use ($request) {
                    $url = config('nova.path').static::redirectAfterUpdate($request, $this);
                    return "<a href=\"{$url}\" class=\"text-inherit no-underline hover:underline\">{$value}</a>";
                }),

            Text::make(__('Views'), 'views_count')
                ->sortable()
                ->onlyOnIndex()
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            Text::make(__('post_edit_lock.index_column'), 'id')
                ->onlyOnIndex()
                ->sortable(false)
                ->asHtml()
                ->displayUsing(function ($value, $resource) use ($request) {
                    $postKey = PostEditLockService::makePostKey(static::uriKey(), (string) $resource->getKey());
                    $uid = auth()->id();
                    
                    $url = config('nova.path').static::redirectAfterUpdate($request, $this);
                    $inner = app(PostEditLockService::class)->indexLockColumnHtml($postKey, $uid !== null ? (int) $uid : null);

                    return "<a href=\"{$url}\" class=\"text-inherit no-underline hover:underline inline-flex flex-col items-start\">{$inner}</a>";
                }),

            BelongsTo::make(__('Translation'), 'translation', PostCollection::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->fullWidth()
                ->searchable()
                ->nullable()
                ->withSubtitles()
                ->relatableQueryUsing(function (NovaRequest $request, Builder $query) use ($locale) {
                    $query->where('language_code', $locale === 'en' ? 'ru' : 'en');
                    $query->where('type', static::getPostType());
                }),

            TagField::make(__('Tags'), 'tags', Tag::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->fullWidth()
                ->searchable()
                ->nullable()
                ->preload(),

            BelongsTo::make(__('Investigation Theme'), 'investigationtheme', InvestigationTheme::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->fullWidth()
                ->searchable()
                ->nullable(),

            Heading::make('<h3 style="margin-top: 50px;" class="uppercase tracking-wide font-bold text-s">SEO</h3>')
                ->hideFromDetail()
                ->asHtml(),

            Text::make('Slug (URL)', 'slug')
                ->onlyOnForms()
                ->sortable()
                ->fullWidth()
                ->rules('max:140'),

            Text::make(__('Page title'), 'seo_title')
                ->hideFromIndex()
                ->hideFromDetail()
                ->sortable()
                ->fullWidth()
                ->rules('max:140')
                ->help(__('Autosave is enabled for this field.'))
                ->withMeta(['extraAttributes' => [
                    'data-post-seo-title' => '1',
                    'data-post-autosave-field' => '1',
                ]]),

            Text::make(__('Page description'), 'seo_description')
                ->hideFromIndex()
                ->hideFromDetail()
                ->sortable()
                ->fullWidth()
                ->rules('max:255')
                ->help(__('Autosave is enabled for this field.'))
                ->withMeta(['extraAttributes' => [
                    'data-post-seo-description' => '1',
                    'data-post-autosave-field' => '1',
                ]]),

            Text::make(__('Page keywords'), 'seo_keywords')
                ->hideFromIndex()
                ->hideFromDetail()
                ->sortable()
                ->fullWidth()
                ->rules('max:255')
                ->help(__('Autosave is enabled for this field.'))
                ->withMeta(['extraAttributes' => ['data-post-autosave-field' => '1']]),
        ];

        // Create: без табов — одна форма. Edit: вкладки Общее / Контент / Настройки.
        // Nova вызывает newResource() с пустой моделью для attachable/associatable/morphable,
        // при этом resourceId в маршруте есть — без этого условия поля из вкладки «Настройки» (теги и т.д.)
        // не попадают в availableFieldsOnIndexOrDetail и API отдаёт 404.
        $expandPublicationTabs = $this->shouldExpandPublicationTabs($request);

        if ($expandPublicationTabs) {
            if (static::getPostType() !== PostTypes::ONLINE) {
                $publicationGroup = Tab::group(fields: [
                    Tab::make(__('General'), $general, 'general'),
                    Tab::make(__('Content'), $content, 'content'),
                    Tab::make(__('Settings'), $settings, 'settings'),
                ]);
            } else {
                $publicationGroup = Tab::group(fields: [
                    Tab::make(__('General'), $general, 'general'),
                    Tab::make(__('Settings'), $settings, 'settings'),
                ]);
            }
        } else {
            $publicationFields = PanelWithoutHeader::make($general);
        }

        // Access
        $access = Panel::make(__('Access settings'), [
            TagField::make(__('Management access'), 'owners', User::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->searchable()
                ->nullable()
                ->preload()
                ->withSubtitles()
                ->resolveUsing(function ($value, $resource) {
                    $user = auth()->user();
                    if ($user === null) {
                        return $value;
                    }

                    if (! $resource->exists) {
                        return $value ?: [
                            ['display' => $user->name, 'value' => $user->id],
                        ];
                    }

                    $ids = collect($value ?? [])->pluck('value')->map(fn ($id) => (int) $id)->all();

                    if (! in_array((int) $user->id, $ids, true)) {
                        return array_merge($value ?? [], [
                            ['display' => $user->name, 'value' => $user->id],
                        ]);
                    }

                    return $value;
                })
                ->fillUsing(function (NovaRequest $request, $model, $attribute, $requestAttribute) {
                    return function () use ($request, $model, $attribute, $requestAttribute) {
                        $ids = [];
                        if ($request->filled($requestAttribute)) {
                            $decoded = json_decode($request[$requestAttribute], true);
                            if (is_array($decoded)) {
                                $ids = collect($decoded)
                                    ->pluck('value')
                                    ->filter()
                                    ->map(fn ($id) => (int) $id)
                                    ->all();
                            }
                        }
                        $userId = auth()->id();
                        if ($userId !== null && ! in_array((int) $userId, $ids, true)) {
                            $ids[] = (int) $userId;
                        }
                        $model->{$attribute}()->sync($ids);
                    };
                }),
        ]);

        // Render

        $postEditTitleRow = PageTitle::make($this, 'PostEditTitleRow', [
            Hidden::make(__('Language'), 'language_code')->default($locale),
            Hidden::make(__('Type'), 'type')->default(static::getPostType()),
        ]);

        return [
            $postEditTitleRow,
            $FormActionBarTop,
            $expandPublicationTabs ? $publicationGroup : $publicationFields,
            $FormActionBarBottom,
            $access,
        ];
    }

    public function fieldsForDetail(Request $request): array
    {
        return [
            PostHistory::make(),
            PanelWithoutHeader::make([
                Text::make(__('Date created'), 'created_at')->resolveUsing(function () {
                    return $this->created_at->format('d.m.Y H:i:s');
                }),
                Text::make(__('Publication date'), 'published_at')->resolveUsing(function () {
                    return $this->status == 'published' ? $this->published_at->format('d.m.Y H:i:s') : __(ucfirst($this->status));
                }),
                Text::make(__('Category'), 'category')->resolveUsing(function () {
                    return $this->category->title;
                }),
                Text::make(__('Title'), 'title'),
                Text::make(__('Authors'), 'authors')->resolveUsing(function () {
                    return $this->authors->pluck('fullname')->implode(', ');
                }),
                Text::make(__('Views'), 'views_count')->resolveUsing(function () {
                    return $this->views_count;
                }),
                Text::make(__('Image file'), 'image')->resolveUsing(function () {
                    return $this->image
                        ? '<img src="'.e(\Storage::disk('public')->url($this->image)).'" style="max-width: 512px; height: auto; display:block;" />'
                        : null;
                })->asHtml(),
            ]),
        ];
    }

    /**
     * Полная схема полей (вкладки) нужна не только при $this->exists: Nova для relatable API
     * строит ресурс с newModel(), поэтому без проверки запроса поля из «Настроек» отсутствуют в ответе.
     */
    /**
     * Nova edit lock (Redis): HTML статуса лока + data-* meta для FormActionBar / JS.
     *
     * @return array{lockContentHtml: string, initialCanEdit: bool, meta: array<string, string>}|null
     */
    protected function buildPostEditLockFormOptions(Request $request, ?string $postUrl): ?array
    {
        if (! $this->exists) {
            return null;
        }

        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $postKey = PostEditLockService::makePostKey(static::uriKey(), (string) $this->resource->getKey());
        $service = app(PostEditLockService::class);
        $lockState = $service->claimIfMissing($postKey, $user);

        $canEdit = (int) $lockState['editor_user_id'] === (int) $user->getAuthIdentifier();
        $lockVersion = (int) ($lockState['lock_version'] ?? 1);

        if ($canEdit) {
            $ttl = $service->remainingTtl($postKey);
            $expiresAt = Carbon::now()->utc()->addSeconds($ttl);
            $userTz = $user->timezone ?? config('app.timezone');
            if ($userTz && in_array($userTz, timezone_identifiers_list(), true)) {
                $expiresAt = $expiresAt->copy()->setTimezone($userTz);
            }
            $timeStr = $expiresAt->format('H:i:s');

            $lastSavedAt = $lockState['last_edited_at'] ?? null;
            $lastEditedDt = null;
            if (! empty($lastSavedAt)) {
                try {
                    $lastEditedDt = Carbon::parse($lastSavedAt);
                } catch (\Throwable) {
                    $lastEditedDt = null;
                }
            }
            if ($lastEditedDt === null && $this->resource->updated_at) {
                $lastEditedDt = $this->resource->updated_at->copy();
            }
            if ($lastEditedDt !== null) {
                if ($userTz && in_array($userTz, timezone_identifiers_list(), true)) {
                    $lastEditedDt = $lastEditedDt->copy()->setTimezone($userTz);
                }
            }

            $lastSavedLine = '';
            if ($lastEditedDt !== null) {
                $lastSavedLine = '<div class="nova-post-edit-lock__line">'
                    .'<span class="nova-post-edit-lock__label">'.e(__('post_edit_lock.last_edited_label')).'</span>'
                    .'<span class="nova-post-edit-lock__value nova-post-edit-lock__time" data-lock-last-saved="1">'
                    .e($lastEditedDt->format('d.m.Y H:i:s'))
                    .'</span></div>';
            }

            $lockContentHtml = '<div class="nova-post-edit-lock nova-post-edit-lock--editor">'
                .'<div class="nova-post-edit-lock__line nova-post-edit-lock__line--locked-until">'
                .'<span class="nova-post-edit-lock__label">'.e(__('post_edit_lock.locked_until_label')).'</span>'
                .'<span class="nova-post-edit-lock__value nova-post-edit-lock__time">'.e($timeStr).'</span>'
                .'<button type="button" class="nova-post-edit-lock__exit">'.e(__('post_edit_lock.exit_edit')).'</button>'
                .'</div>'
                .$lastSavedLine
                .'</div>';
        } else {
            $editorName = (string) ($lockState['editor_name'] ?? '');
            $editorEmail = (string) ($lockState['editor_email'] ?? '');

            $onlineLine = '';
            $heartbeatAt = $lockState['last_heartbeat_at'] ?? null;
            if (! empty($heartbeatAt)) {
                try {
                    $secondsTotal = (int) floor(max(0, Carbon::parse($heartbeatAt)->diffInSeconds(now())));
                    $onlineText = $secondsTotal <= 0
                        ? __('post_edit_lock.index_last_seen_just_now')
                        : (intdiv($secondsTotal, 60) <= 0
                            ? __('post_edit_lock.index_last_seen_seconds_ago', [
                                'seconds' => $secondsTotal % 60,
                            ])
                            : __('post_edit_lock.index_last_seen_minutes_seconds_ago', [
                                'minutes' => intdiv($secondsTotal, 60),
                                'seconds' => $secondsTotal % 60,
                            ]));
                    $onlineLine = '<div class="nova-post-edit-lock__line">'
                        .'<span class="nova-post-edit-lock__label">'.e(__('post_edit_lock.last_seen_online_label')).'</span>'
                        .'<span class="nova-post-edit-lock__value nova-post-edit-lock__time">'
                        .e($onlineText)
                        .'</span></div>';
                } catch (\Throwable) {
                    $onlineLine = '';
                }
            }
            if ($onlineLine === '') {
                $onlineLine = '<div class="nova-post-edit-lock__line">'
                    .'<span class="nova-post-edit-lock__label">'.e(__('post_edit_lock.last_seen_online_label')).'</span>'
                    .'<span class="nova-post-edit-lock__value nova-post-edit-lock__time">—</span></div>';
            }

            $lockContentHtml = '<div class="nova-post-edit-lock nova-post-edit-lock--readonly">';
            $lockContentHtml .= '<div class="nova-post-edit-lock__line">'
                .'<span class="nova-post-edit-lock__label">'.e(__('post_edit_lock.readonly_label')).'</span>'
                .'<span class="nova-post-edit-lock__value">'
                .e($editorName !== '' ? $editorName : '—')
                .' <span class="nova-post-edit-lock__muted">'.e($editorEmail !== '' ? $editorEmail : '—').'</span>'
                .'</span></div>';
            $lockContentHtml .= $onlineLine;
            $lockContentHtml .= '</div>';
        }

        // Отдельные data-pe-msg-* вместо JSON в одном атрибуте: длинный JSON + e() ломал parse в JS (пустые подписи).
        $meta = [
            'post-edit-lock' => '1',
            'post-key' => $postKey,
            'lock-version' => (string) $lockVersion,
            'heartbeat-url' => route('nova.post-edit-lock.heartbeat'),
            'takeover-url' => route('nova.post-edit-lock.takeover'),
            'release-url' => route('nova.post-edit-lock.release'),
            'pe-msg-editing' => __('post_edit_lock.editing_locked_with_expiry', ['time' => ':time']),
            'pe-msg-locked-until-label' => __('post_edit_lock.locked_until_label'),
            'pe-msg-readonly-label' => __('post_edit_lock.readonly_label'),
            'pe-msg-readonly-intro' => __('post_edit_lock.readonly_intro', ['name' => ':name', 'email' => ':email']),
            'pe-msg-last-edited' => __('post_edit_lock.last_edited_label'),
            'pe-msg-last-seen-online' => __('post_edit_lock.last_seen_online_label'),
            'pe-msg-last-seen-unknown' => __('post_edit_lock.index_last_seen_unknown'),
            'pe-msg-last-seen-just-now' => __('post_edit_lock.index_last_seen_just_now'),
            'pe-msg-last-seen-min-sec-ago' => __('post_edit_lock.index_last_seen_minutes_seconds_ago', [
                'minutes' => ':minutes',
                'seconds' => ':seconds',
            ]),
            'pe-msg-last-seen-sec-ago' => __('post_edit_lock.index_last_seen_seconds_ago', [
                'seconds' => ':seconds',
            ]),
            'pe-msg-takeover' => __('post_edit_lock.takeover'),
            'pe-msg-takeover-confirm' => __('post_edit_lock.takeover_confirm'),
            'pe-msg-takeover-done-title' => __('post_edit_lock.takeover_done_title'),
            'pe-msg-takeover-done-body' => __('post_edit_lock.takeover_done_body'),
            'pe-msg-displaced-title' => __('post_edit_lock.displaced_title'),
            'pe-msg-displaced-body' => __('post_edit_lock.displaced_body'),
            'pe-msg-publication-freed-line1' => __('post_edit_lock.publication_freed_line1'),
            'pe-msg-publication-freed-as-of-label' => __('post_edit_lock.publication_freed_as_of_label'),
            'pe-msg-reload-to-edit' => __('post_edit_lock.reload_to_edit_button'),
            'pe-msg-exit-edit' => __('post_edit_lock.exit_edit'),
        ];

        return [
            'lockContentHtml' => $lockContentHtml,
            'initialCanEdit' => $canEdit,
            'meta' => $meta,
        ];
    }

    public static function afterUpdate(NovaRequest $request, Model $model): void
    {
        if (! $request instanceof UpdateResourceRequest) {
            return;
        }

        $resource = $request->newResourceWith($model);
        if (! $resource instanceof static) {
            return;
        }

        $postKey = PostEditLockService::makePostKey($resource::uriKey(), (string) $model->getKey());
        app(PostEditLockService::class)->recordLastEdited($postKey, $request->user());
    }

    protected function shouldExpandPublicationTabs(Request $request): bool
    {
        if ($this->exists) {
            return true;
        }

        if (! $request instanceof NovaRequest) {
            return false;
        }

        if (filled($request->resourceId)) {
            return true;
        }

        $path = $request->path();

        return str_contains($path, '/associatable/')
            || str_contains($path, '/attachable/')
            || str_contains($path, '/morphable/');
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        $path = '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';

        if (static::getPostType() !== PostTypes::ONLINE) {
            $path .= '?nova_tab=content';
        }

        return $path;
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function createButtonLabel(): string
    {
        return __('Create');
    }

    public static function updateButtonLabel(): string
    {
        return __('Save');
    }

    protected static function formatPublishedAtForUser($publishedAt): ?string
    {
        if (! $publishedAt) {
            return null;
        }

        $userTz = auth()->user()->timezone ?? config('app.timezone');

        if ($userTz && in_array($userTz, timezone_identifiers_list(), true)) {
            $publishedAt = $publishedAt->copy()->setTimezone($userTz);
        }

        return $publishedAt->format('d.m.Y H:i:s');
    }

    public static function defaultOrderings(\Illuminate\Contracts\Database\Eloquent\Builder $query): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        return $query->orderByRaw('published_at DESC NULLS LAST');
    }

    public static function usesScout(): bool
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, \Illuminate\Contracts\Database\Eloquent\Builder $query): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        $query->where('language_code', static::resolveResourceLanguageCodeForRequest($request));

        if (static::getPostType()) {
            $query->where('type', static::getPostType());
        }

        if (! $request->user()->isAdmin() && ! $request->user()->isEditor()) {
            $query->whereHas('owners', function ($q) {
                $q->where('user_id', auth()->user()->id);
            });
        }

        return $query->with('category');
    }
}
