<?php

namespace App\Nova\_Posts;

use Laravel\Nova\Resource;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

use Laravel\Nova\Panel;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Tag as TagField;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

use Mostafaznv\NovaCkEditor\CkEditor;
use Medov\ImageGallery\ImageGallery;

use Medov\DateTimeSplit\DateTimeSplit;

use App\Nova\_Users\User;

class OnlineMessage extends Resource
{
    public static $model = \App\Models\PostTypes\OnlineMessage::class;

    public static $title = 'published_at';
    public static $search = ['id', 'text'];

    public static $clickAction = 'edit';

    public function fields(NovaRequest $request): array
    {
        return [
            Hidden::make(__('Language'), 'language_code')->default(app()->getLocale()),

            BelongsTo::make(__('Online'), 'online', PostOnline::class)
                ->onlyOnForms(),

            Text::make(__('Title'), 'online')
                ->exceptOnForms()
                ->sortable()
                ->rules('required', 'max:255')
                ->displayUsing(function ($online, $resource) use ($request) {
                    $url =  config('nova.path') . static::redirectAfterUpdate($request, $this);
                    return "<a href='{$url}'><div class='nova_view_post_title'>{$online->title}</div></a>";
                })
                ->asHtml(),

            DateTimeSplit::make(__('Publication date'), 'published_at')
                ->default(now())
                ->rules('required'),
                
            Panel::make(__('Key event'), [
                Boolean::make(__('Enable'), 'is_key_event')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked()
                    ->sortable()
                    ->rules('boolean')
                    ->help(__('The event text will be emphasized with increased font size and bold styling. A corresponding link will be incorporated into the table of contents for enhanced navigation.')),

                Badge::make(__('Enable'), 'is_key_event')->types([
                        false => 'font-medium text-gray-600',
                        true => ['font-bold', 'text-red-600'],
                    ])->label(function ($value) {
                        if ($value) {
                            return __('Key event');
                        } else {
                            return __('General event');
                        }
                    }),

                Text::make(__('Outline'), 'outline')
                    ->fullWidth()
                    ->stacked()
                    ->hide()
                    ->dependsOn(
                        ['is_key_event'],
                        function (Text $field, NovaRequest $request, FormData $formData) {
                            if ($formData->is_key_event) {
                                $field->show()->rules(['required']);
                            } else {
                                $field->hide()->nullable();
                            }
                        }
                    ),
            ]),

            Panel::make(__('Text'), [
                CkEditor::make(__('Text'), 'text')
                    ->fullWidth()
                    ->stacked(),
            ]),

            Panel::make(__('Images'), [
                ImageGallery::make(__('Image list'), 'images')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked()
                    ->rules('nullable'),
                    
            ]),

            Panel::make(__('Video'), [
                Text::make(__('Video URL'), 'video_url')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked()
                    ->rules('nullable', 'sometimes', 'url'),
            
                Text::make(__('Description'), 'video_description')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked()
                    ->nullable()
                    ->hide()
                    ->dependsOn(
                        ['video_url'],
                        function (Text $field, NovaRequest $request, FormData $formData) {
                            if (!empty($formData->video_url)) {
                                $field->show();
                            } else {
                                $field->hide();
                            }
                        }
                    ),
            
                Text::make(__('Author'), 'video_author')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked()
                    ->nullable()
                    ->hide()
                    ->dependsOn(
                        ['video_url'],
                        function (Text $field, NovaRequest $request, FormData $formData) {
                            if (!empty($formData->video_url)) {
                                $field->show();
                            } else {
                                $field->hide();
                            }
                        }
                    ),
            ]),


            Panel::make(__('IFrame / Embed'), [
                Textarea::make(__('Embed code'), 'embed_code')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked(),

                Select::make(__('Embed type'), 'embed_type')
                    ->onlyOnForms()
                    ->fullWidth()
                    ->stacked()
                    ->nullable()
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
                    ->displayUsingLabels()
                    ->default('hidden')
                    ->hide()
                    ->dependsOn(
                        ['embed_code'],
                        function (Select $field, NovaRequest $request, FormData $formData) {
                            if (!empty($formData->embed_code)) {
                                $field->show();
                            } else {
                                $field->hide();
                            }
                        }
                    ),
            ]),

            
        ];
    }

    public static function redirectAfterCreate(NovaRequest $request, Resource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function redirectAfterUpdate(NovaRequest $request, Resource $resource)
    {
        return '/resources/'.static::uriKey().'/'.$resource->getKey().'/edit';
    }

    public static function label() {
        return __('Online Messages');
    }

    public static function singularLabel() {
        return __('Online Message');
    }

    public static function indexQuery(NovaRequest $request, $query) {
        $query->where('language_code', app()->getLocale());

        // if (!$request->user()->canViewAll()) {
        //     $query->whereHas('owners', function($q) {
        //         $q->where('user_id', auth()->user()->id);
        //     });
        // }

        return $query;
    }
}
