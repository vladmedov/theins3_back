<?php

namespace App\Http\Controllers\Nova;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Post;
use App\Models\PostTypes\PostArticle;
use App\Models\PostTypes\PostNews;
use App\Models\PostTypes\PostOpinion;
use App\Models\PostTypes\PostOnline;

use App\Enums\PostTypes;
use App\Enums\UserRoles;

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
        return [
            'post_types' => [
                ['label' => __('Articles'), 'value' => PostTypes::ARTICLE],
                ['label' => __('News'), 'value' => PostTypes::NEWS],
                ['label' => __('Opinions'), 'value' => PostTypes::OPINION],
                ['label' => __('Onlines'), 'value' => PostTypes::ONLINE],
            ],
            'authors' => User::all()->filter(function ($user) {
                return $user->hasRole([UserRoles::AUTHOR, UserRoles::NEWS_WRITER]) || $user->canViewAll();
            })->map(function ($author) {
                return [
                    'id' => $author->id,
                    'name' => $author->full_name,
                ];
            })->values(),
            'columnists' => User::all()->filter(function ($user) {
                return $user->hasRole(UserRoles::COLUMNIST);
            })->map(function ($columnist) {
                return [
                    'id' => $columnist->id,
                    'name' => $columnist->full_name,
                ];
            })->values(),
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

        if ($request->resource) {
            $query->where('type', $request->resource);
        }

        if ($request->start && $request->end) {
            $query->whereBetween('published_at', [$request->start, $request->end]);
        }

        if ($request->author_id && $request->resource !== PostTypes::OPINION) {
            $query->whereHas('authors', function ($q) use ($request) {
                $q->where('users.id', $request->author_id);
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
                $authorName = $event->columnist ? $event->columnist->full_name : 'The Insider';
            } else {
                $firstAuthor = $event->authors->first();
                $authorName = $firstAuthor ? $firstAuthor->full_name : 'The Insider';
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
