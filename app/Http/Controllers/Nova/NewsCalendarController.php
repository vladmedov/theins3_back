<?php

namespace App\Http\Controllers\Nova;

use Illuminate\Http\Request;

use App\Models\Author;
use App\Models\Post;
use App\Models\PostTypes\PostArticle;
use App\Models\PostTypes\PostNews;
use App\Models\PostTypes\PostOpinion;
use App\Models\PostTypes\PostOnline;

use App\Enums\PostTypes;

use App\Http\Controllers\Controller;

class NewsCalendarController extends Controller
{
    protected $models = [
        PostTypes::ARTICLE => PostArticle::class,
        PostTypes::NEWS => PostNews::class,
        PostTypes::OPINION => PostOpinion::class,
        PostTypes::ONLINE => PostOnline::class,
    ];

    protected $resources = [
        PostTypes::ARTICLE => 'post-articles',
        PostTypes::NEWS => 'post-news',
        PostTypes::OPINION => 'post-opinions',
        PostTypes::ONLINE => 'post-onlines',
    ];

    public function getResources()
    {
        $locale = app()->getLocale();

        return [
            'post_types' => [
                ['label' => __('Articles'), 'value' => PostTypes::ARTICLE],
                ['label' => __('News'), 'value' => PostTypes::NEWS],
                ['label' => __('Opinions'), 'value' => PostTypes::OPINION],
                ['label' => __('Onlines'), 'value' => PostTypes::ONLINE],
            ],
            'authors' => Author::where('language_code', $locale)
                ->orderBy('last_name')
                ->get()
                ->filter(fn($a) => !empty(array_intersect($a->allowed_post_types ?? [], [
                    PostTypes::ARTICLE,
                    PostTypes::NEWS,
                    PostTypes::ONLINE,
                ])))
                ->map(fn($a) => ['id' => $a->id, 'name' => $a->full_name])
                ->values(),
            'columnists' => Author::where('language_code', $locale)
                ->whereJsonContains('allowed_post_types', PostTypes::OPINION)
                ->orderBy('last_name')
                ->get()
                ->map(fn($a) => ['id' => $a->id, 'name' => $a->full_name])
                ->values(),
        ];
    }

    public function getEvents(Request $request)
    {
        if (isset($this->models[$request->resource])) {
            $model = $this->models[$request->resource];
        } else {
            $model = Post::class;
        }

        $query = $model::query();

        $query->where('language_code', app()->getLocale());
        $query->where('status', Post::STATUS_PUBLISHED);

        if ($request->resource) {
            $query->where('type', $request->resource);
        }

        if ($request->start && $request->end) {
            $query->whereBetween('published_at', [$request->start, $request->end]);
        }

        if ($request->author_id && $request->resource !== PostTypes::OPINION) {
            $query->whereHas('authors', function ($q) use ($request) {
                $q->where('authors.id', $request->author_id);
            });
        }

        if ($request->columnist_id && $request->resource === PostTypes::OPINION) {
            $query->where('columnist_id', $request->columnist_id);
        }

        $totalEvents = $query->count();
        $totalViews = $query->sum('views_count');

        $query->orderBy('published_at', 'DESC')->orderBy('title', 'ASC');

        $events = $query->get()->map(function ($event) use ($request) {
            if ($event->type === PostTypes::OPINION) {
                $authorName = $event->columnist ? $event->columnist->full_name : '—';
            } else {
                $firstAuthor = $event->authors->first();
                if ($firstAuthor) {
                    $authorName = $firstAuthor->full_name;
                    if ($event->authors->count() > 1) {
                        $authorName .= ' [+]';
                    }
                } else {
                    $authorName = '—';
                }
            }

            return [
                'title' => $event->title,
                'author' => $authorName,
                'views_count' => $event->views_count,
                'start' => $event->published_at,
                'url' => route('nova.pages.edit', [
                    'resource' => $this->resources[$event->type] ?? 'posts',
                    'resourceId' => $event->id
                ]),
            ];
        });

        return response()->json([
            'events' => $events,
            'totalEvents' => $totalEvents,
            'totalViews' => $totalViews
        ]);
    }

}
