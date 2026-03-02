<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\Author;
use App\Models\CollectionPost;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;
use App\Http\Resources\ColumnistResource;

class WidgetController extends Controller
{
    public function getTopNews($language_code)
    {
        $limit = 5;

        $forcedPosts = CollectionPost
            ::where('language_code', $language_code)
            ->where('collection_code', CollectionPost::COLLECTION_CODE_TOP_NEWS)
            ->orderBy('position', 'asc')
            ->limit($limit)
            ->get()
            ->pluck('post_id');

        $query = Post
            ::whereIn('id', $forcedPosts)
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $language_code)
            ->where('type', PostTypes::NEWS)
            ->with(['category', 'authors']);

        $forcedPostsCollection = $query->get()
            ->sortBy(function($post) use ($forcedPosts) {
                return array_search($post->id, $forcedPosts->toArray());
            });
        
        if ($forcedPostsCollection->count() < $limit) {
            $additionalPosts = Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('type', PostTypes::NEWS)
                ->where('published_at', '>=', now()->subWeek()) // За последнюю неделю
                ->whereNotIn('id', $forcedPosts)
                ->with(['category', 'authors'])
                ->orderBy('views_count', 'desc')
                ->limit($limit - $forcedPostsCollection->count())
                ->get();
            
            $posts = $forcedPostsCollection->concat($additionalPosts);
        } else {
            $posts = $forcedPostsCollection->take($limit);
        }
        
        return PostResource::collection($posts);
    }
    
    public function getPopular($language_code)
    {
        $limit = 5;

        $forcedPosts = CollectionPost
            ::where('language_code', $language_code)
            ->where('collection_code', CollectionPost::COLLECTION_CODE_POPULAR)
            ->orderBy('position', 'asc')
            ->limit($limit)
            ->get()
            ->pluck('post_id');

        $query = Post
            ::whereIn('id', $forcedPosts)
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $language_code)
            ->with(['category', 'authors', 'columnist']);

        $forcedPostsCollection = $query->get()
            ->sortBy(function($post) use ($forcedPosts) {
                return array_search($post->id, $forcedPosts->toArray());
            });
        
        if ($forcedPostsCollection->count() < $limit) {
            $additionalPosts = Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('published_at', '>=', now()->subMonth()) // За последний месяц
                ->whereNotIn('id', $forcedPosts)
                ->with(['category', 'authors', 'columnist'])
                ->orderBy('views_count', 'desc')
                ->limit($limit - $forcedPostsCollection->count())
                ->get();
            
            $posts = $forcedPostsCollection->concat($additionalPosts);
        } else {
            $posts = $forcedPostsCollection->take($limit);
        }
        
        return PostResource::collection($posts);
    }
    
    public function getOpinions($language_code)
    {
        $limit = 3;

        $forcedPosts = CollectionPost
            ::where('language_code', $language_code)
            ->where('collection_code', CollectionPost::COLLECTION_CODE_MAIN_OPINIONS)
            ->orderBy('position', 'asc')
            ->limit($limit)
            ->get()
            ->pluck('post_id');

        $query = Post
            ::whereIn('id', $forcedPosts)
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $language_code)
            ->where('type', PostTypes::OPINION)
            ->with(['category', 'columnist']);

        $forcedPostsCollection = $query->get()
            ->sortBy(function($post) use ($forcedPosts) {
                return array_search($post->id, $forcedPosts->toArray());
            });
        
        if ($forcedPostsCollection->count() < $limit) {
            $additionalPosts = Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('type', PostTypes::OPINION)
                ->with(['category', 'columnist'])
                ->whereNotIn('id', $forcedPosts)
                ->orderBy('published_at', 'desc')
                ->limit($limit - $forcedPostsCollection->count())
                ->get();
            
            $posts = $forcedPostsCollection->concat($additionalPosts);
        } else {
            $posts = $forcedPostsCollection->take($limit);
        }
        
        return PostResource::collection($posts);
    }
    
    public function getColumnists($language_code)
    {
        $columnistsData = DB::table('authors')
            ->join('posts', 'authors.id', '=', 'posts.columnist_id')
            ->where('authors.language_code', $language_code)
            ->where('authors.slug', '!=', 'the-insider')
            ->where('posts.language_code', $language_code)
            ->where('posts.type', PostTypes::OPINION)
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->select('authors.id')
            ->selectRaw('COUNT(posts.id) as opinions_count')
            ->groupBy('authors.id')
            ->orderByRaw('opinions_count DESC')
            ->get()
            ->filter(function ($item) {
                return $item->opinions_count >= 3;
            });
        
        $columnistsIds = $columnistsData->pluck('id');
        
        $columnists = Author::whereIn('id', $columnistsIds)
            ->where('is_columnist_page_hidden', false)
            ->get()
            ->sortBy(function ($author) use ($columnistsIds) {
                return $columnistsIds->search($author->id);
            })
            ->values();
        
        return ColumnistResource::collection($columnists);
    }
}

