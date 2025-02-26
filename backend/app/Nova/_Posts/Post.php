<?php

namespace App\Nova\_Posts;

use Laravel\Nova\Resource;
use Illuminate\Support\Facades\Storage;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Tag as TagField;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Tabs\Tab;
use Laravel\Nova\Panel;
use Illuminate\Http\Request;

use Emilianotisato\NovaTinyMCE\NovaTinyMCE;
use Mostafaznv\NovaCkEditor\CkEditor;
use Medov\ImageGallery\ImageGallery;
use Medov\DateTimeSplit\DateTimeSplit;

use Illuminate\Support\Facades\Log;

use App\Nova\Repeaters;
use Laravel\Nova\Fields\Repeater;

use Whitecube\NovaFlexibleContent\Flexible;

// use Spatie\MediaLibrary\MediaCollections\Models\Media;
// use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
// use Ebess\AdvancedNovaMediaLibrary\Fields\Images;


use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Database\Eloquent\Builder;

use App\Nova\Flexible\Layouts\GalleryLayout;

use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\FieldCollection;

use Laravel\Nova\Resource as NovaResource;

use Laravel\Nova\Notifications\NovaNotification;
use Illuminate\Database\Eloquent\Model;

use Outl1ne\MultiselectField\Multiselect as EntityMultiselect;

use Carbon\Carbon;

use App\Enums\UserRoles;
use App\Enums\PostTypes;
use App\Enums\CategoryTypes;

use App\Nova\_Collections\PostCollection;
use App\Nova\_Users\User;
use App\Nova\_Users\UserAuthor;
use App\Nova\_Users\UserColumnist;
use App\Nova\_Taxonomy\Category;
use App\Nova\_Taxonomy\InvestigationTheme;
use App\Nova\_Taxonomy\Tag;
use App\Nova\_Taxonomy\Termin;

use App\Nova\Metrics\PostsPerDay;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Nova;

use Medov\PostHistory\PostHistory;

use App\Services\ImageService;

//use Outl1ne\NovaSortable\Traits\HasSortableManyToManyRows;

abstract class Post extends Resource
{
    // use HasSortableManyToManyRows;
    // public static $sortableCacheEnabled = false;

    // public $sortable = [
    //     'only_sort_on' => \App\Nova\_Users\User::class,
    // ];

    public static $title = 'title';
    public static $search = ['id', 'title', 'lead'];

    public static $clickAction = 'edit';

    public static function getPostType()
    {
        if (defined(static::$model . '::TYPE')) {
            return constant(static::$model . '::TYPE');
        }
        return null;
    }

    public function filters(Request $request)
    {
        $filters = [];

        if (CategoryTypes::isDefault(static::getPostType())) {
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

    public function fields(Request $request) {

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
                ->rules('required'),

            Text::make(__('Publication date'), 'published_at')
                ->exceptOnForms()
                ->sortable()
                ->rules('required', 'max:255')
                ->displayUsing(function ($date, $resource) use ($request) {
                    $url =  config('nova.path') . static::redirectAfterUpdate($request, $this);
                    return $resource->status == 'published' 
                        ? "<a href='{$url}'><span class='font-bold text-green-600'>{$date->format('d.m.Y H:i:s')}</span>" 
                        : "<a href='{$url}'><span class='font-bold text-red-600'>{$date->format('d.m.Y H:i:s')}</span></a>";
                })
                ->asHtml(),

            Text::make(__('Title'), 'title')
                ->sortable()
                ->rules('required', 'max:255')
                ->displayUsing(function ($title, $resource) use ($request) {
                    $url =  config('nova.path') . static::redirectAfterUpdate($request, $this);
                    return $resource->is_super_news 
                        ? "<a href='{$url}'><div class='nova_view_post_title font-bold'>{$title}</div>" 
                        : "<a href='{$url}'><div class='nova_view_post_title'>{$title}</div></a>";
                })
                ->asHtml(),

            \App\Models\Category
                ::where('type', CategoryTypes::getCategoryTypeByPostType(static::getPostType()))
                ->where('language_code', app()->getLocale())
                ->count() === 1
                    ? Hidden::make(__('Category'), 'category_id')
                        ->default(function () {
                            $category = \App\Models\Category
                                ::where('type', static::getPostType())
                                ->where('language_code', app()->getLocale())
                                ->first();
                            return $category->id;
                        })
                    : BelongsTo::make(__('Category'), 'category', Category::class)
                        ->relatableQueryUsing(function (Request $request, Builder $query) {
                            $query
                                ->where('type', CategoryTypes::getCategoryTypeByPostType(static::getPostType()))
                                ->where('language_code', app()->getLocale());
                        }),
                
            Text::make(__('Views'), function () {
                return $this->views_count;
                })->hideFromDetail(),
        ];

        if (static::getPostType() == PostTypes::NEWS) {
            $general[] = Boolean::make(__('Super news'), 'is_super_news')
                ->onlyOnForms()
                ->sortable()
                ->rules('boolean')
                ->help(__('The news headline will be displayed in bold.'));
        }

        $content = [
                
            Flexible::make(__('Blocks'), 'content')
                ->hideFromDetail()
                ->menu('custom-flexible-menu')
                ->fullWidth()

                ->addLayout(__('Text'), 'text', [
                    CkEditor::make(__('Text'), 'text')
                        ->fullWidth()
                        ->stacked(),
                ])

                ->addLayout(__('Images'), 'images', [
                    ImageGallery::make(__('Image list'), 'images')
                        ->fullWidth()
                        ->stacked()
                        ->rules('nullable'),
                ])
                ->addLayout(__('Video'), 'video', [
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
                            'ok' => 'OK',
                            'iframe' => 'iFrame',
                        ])
                        ->default('hidden')
                        ->displayUsingLabels()
                        ->rules('required')
                        ->stacked(),
                ])
                
                ->addLayout(__('Outline'), 'outline', [
                    Text::make(__('Outline title'), 'outline')
                    ->fullWidth()
                    ->stacked(),
                ])

                ->addLayout(__('Quote'), 'quote', [
                    Textarea::make(__('Quote'), 'quote')
                        ->fullWidth()
                        ->stacked(),
                    Text::make(__('Quote author'), 'quote_author')
                        ->fullWidth()
                        ->stacked()
                        ->nullable(),
                ])

                ->addLayout(__('Related posts'), 'related', [
                    Text::make(__('Related posts title'), 'related_title', function ($value) {
                            return $value ?? __('Publications on this topic');
                        })
                        ->fullWidth()
                        ->stacked(),

                    EntityMultiselect::make(__('Related posts'), 'related_posts')
                        ->fullWidth()
                        ->stacked()
                        ->saveAsJSON()
                        ->reorderable() 
                        ->asyncResource(static::class),
                ])
        ];

        $settings = [

            Heading::make('<h3 style="margin-top: 0px;" class="uppercase tracking-wide font-bold text-s">' . __('General') . '</h3>')
                ->hideFromDetail()
                ->asHtml(),

            static::getPostType() == PostTypes::OPINION
                ? BelongsTo::make(__('Columnist'), 'columnist', UserColumnist::class)
                    ->hideFromDetail()
                    ->default($request->user()->getKey())
                    ->immutable(function ($request) {
                        return !$request->user()->canViewAll();
                    })
                : EntityMultiselect::make(__('Authors'), 'authors')
                    ->belongsToMany(User::class, true)
                    ->reorderable()
                    ->default(auth()->user())
                    ->when($this->exists, function($field) {
                        return $field->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                            $authors = $request->{$requestAttribute};
                            $syncData = [];
                            if ($authors) {
                                foreach ($authors as $index => $authorId) {
                                    $syncData[$authorId] = ['position' => $index + 1];
                                }
                                $model->authors()->sync($syncData);
                            }
                        });
                    }),

                // TagField::make(__('Authors'), 'authors', User::class)
                //     ->onlyOnForms()
                //     ->nullable()
                //     ->searchable()
                //     ->preload()
                //     ->withSubtitles()
                //     ->resolveUsing(function ($value, $model) {
                //         if (!$model->exists) {
                //             return $value ?: [["display" => auth()->user()->fullname, "value" => auth()->user()->id]];
                //         }
                //         return $value;
                //     }),
                    
            Select::make(__('Author Visibility'), 'author_visibility')
                ->onlyOnForms()
                ->options([
                    'default' => __('Author settings'),
                    'force_hidden' => __('Force Hidden'),
                    'force_shown' => __('Force Shown'),
                ])
                ->default('default')
                ->displayUsingLabels()
                ->help(__('Controls the visibility of authors. Overrides default settings.')),
            
            Text::make(__('Author'),
                function () {
                    if ($this->author) {
                        return $this->author;
                    } else {
                        return '<span style="color:#ccc">The Insider</span>';
                    }
                })
                ->asHtml()
                ->hideFromDetail()
                ->hideFromIndex(function () {
                    return static::getPostType() == PostTypes::OPINION;
                }),

            Image::make(__('Image file'), 'image') 
                ->hideFromDetail()
                ->hideFromIndex()
                ->disk('public')
                ->rules('image', 'mimes:jpeg,png,jpg,webp', 'max:20480', 'dimensions:min_width=800,min_height=100')
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
                ->rules('max:255'),

            Heading::make('<h3 style="margin-top: 50px;" class="uppercase tracking-wide font-bold text-s">' . __('Additional') . '</h3>')
                ->hideFromDetail()
                ->asHtml(),

            TagField::make(__('Tags'), 'tags', Tag::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->searchable()
                ->nullable()
                ->preload(),

            TagField::make(__('Termins'), 'termins', Termin::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->searchable()
                ->nullable()
                ->preload(),

            BelongsTo::make(__('Translation'), 'translation', PostCollection::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->searchable()
                ->nullable()
                ->withSubtitles()
                ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                    $query->where('language_code', app()->getLocale() === 'en' ? 'ru' : 'en');
                    $query->where('type', static::getPostType());
                }),

            BelongsTo::make(__('Investigation Theme'), 'investigationtheme', InvestigationTheme::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->nullable(),

            Text::make('Slug', 'slug')
                ->sortable()
                ->onlyOnForms()
                ->rules('max:255'),

            Textarea::make(__('Lead text'), 'lead')
                ->hideFromDetail()
                ->rules('nullable', 'max:500'),

            Heading::make('<h3 style="margin-top: 50px;" class="uppercase tracking-wide font-bold text-s">SEO</h3>')
                ->hideFromDetail()
                ->asHtml(),
            
            Text::make(__('Page title'), 'seo_title')
                ->hideFromIndex()
                ->hideFromDetail()
                ->sortable()
                ->rules('max:255'),

            Text::make(__('Page description'), 'seo_description')
                ->hideFromIndex()
                ->hideFromDetail()
                ->sortable()
                ->rules('max:255'),

            Text::make(__('Page keywords'), 'seo_keywords')
                ->hideFromIndex()
                ->hideFromDetail()
                ->sortable()
                ->rules('max:255'),
        ];

        $access = [
            TagField::make(__('Management access'), 'owners', User::class)
                ->hideFromIndex()
                ->hideFromDetail()
                ->searchable()
                ->nullable()
                ->preload()
                ->withSubtitles()
                ->resolveUsing(function ($value, $model) {
                    if (!$model->exists) {
                        return $value ?: [["display" => auth()->user()->fullname, "value" => auth()->user()->id]];
                    }
                    return $value;
                })
                ->immutable(fn ($request) => ! $this->authorizedToDelete($request)),
        ];

        if (static::getPostType() !== PostTypes::ONLINE) {
            $publicationGroup = Tab::group(__('Publication'), [
                Tab::make(__('Content'), $content),
                Tab::make(__('Settings'), $settings),
            ]);
        } else {
            $publicationGroup = Panel::make(__('Settings'), $settings);
        }

        return [

            // Text::make(__('Debug'), 'debug')
            //     ->displayUsing(function($value, $resource) {
            //         return "Post type (static): " . static::getPostType() . "<br>" .
            //                "Post type (model): " . $resource->type . "<br>" .
            //                "Resource URI Key: " . static::uriKey() . "<br>";
            //     })
            //     ->asHtml(),
                
            Hidden::make(__('Language'), 'language_code')->default(app()->getLocale()),
            Hidden::make(__('Type'), 'type')->default(static::getPostType()),

            Panel::make(__('Publication settings'), $general),
            PostHistory::make(),

            $publicationGroup,

            Panel::make(__('Access settings'), $access),
        ];
    }

    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function indexQuery(NovaRequest $request, $query) {
        if (static::getPostType()) {
            $query->where('language_code', app()->getLocale());
            $query->where('type', static::getPostType());

            if (!$request->user()->canViewAll()) {
                $query->whereHas('owners', function($q) {
                    $q->where('user_id', auth()->user()->id);
                });
            }

            return $query;
        }
    }
}
