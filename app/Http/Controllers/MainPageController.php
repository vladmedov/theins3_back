<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Models\Post;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;
use App\Http\Resources\CategoryResource;

use App\Models\Category;
use App\Models\CollectionPost;
use App\Models\InvestigationTheme;
use App\Models\ExchangeRate;
class MainPageController extends Controller
{
    private const HOT_BLOCK_CACHE_TTL_SECONDS = 60;
    private $excludedIds = [];

    public function getLayoutData($language_code)
    {
        return [
            'rates' => $this->getRates(),
            'categories' => $this->getCategories($language_code),
        ];
    }

    public function getMainPage(Request $request, $language_code)
    {
        $feature = $this->getCollectionPosts(
            $language_code,
            CollectionPost::COLLECTION_CODE_FEATURE,
            3,
            [PostTypes::ARTICLE, PostTypes::OPINION, PostTypes::ONLINE]
        );
        $this->excludedIds = $feature->pluck('id');

        if ($request->has('page')) {
            return $this->getArticles($language_code);
        }

        return [
            'collection_opinions' => $this->getCollectionPosts($language_code, CollectionPost::COLLECTION_CODE_MAIN_OPINIONS, 7, PostTypes::OPINION),
            'collection_feature' => $feature,
            'collection_popular' => $this->getPopular($language_code),
            'main_investigation' => $this->getMainInvestigation($language_code),
            'confessions' => $this->getConfession($language_code),
            'news' => $this->getNews($language_code),
            'articles' => $this->getArticles($language_code),
        ];
    }

    private function getRates()
    {
        return ExchangeRate::getLatestRates();
    }

    private function getCategories($language_code)
    {
        $categories = CategoryResource::collection(
            Category
                ::where('language_code', $language_code)
                ->where('is_show_in_menu', true)
                ->orderBy('position', 'asc')
                ->get()
        )->toArray(request());

        if (count($categories) < 2) {
            return $categories;
        }

        $firstCategories = [$categories[0], $categories[1]];
        unset($categories[0], $categories[1]);

        if ($language_code === 'ru') {
            $militaryTag = [
                [
                    'type' => 'custom',
                    'path' => '/tags/vs',
                    'title' => 'Военная сводка',
                ],
            ];

            return array_merge($firstCategories, $militaryTag, $categories);
        }

        return array_merge($firstCategories, $categories);
    }

    private function getNews($language_code)
    {
        return PostResource::collection(
            Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('type', PostTypes::NEWS)
                ->orderBy('published_at', 'desc')
                ->limit(15)
                ->get()
        );
    }

    private function getCollectionPosts($language_code, $collection_code, $limit, $post_types = null, $sort_by = 'published_at')
    {
        $forcedPosts = CollectionPost
            ::where('language_code', $language_code)
            ->where('collection_code', $collection_code)
            ->orderBy('position', 'asc')
            ->limit($limit)
            ->get()
            ->pluck('post_id');

        $query = Post
            ::whereIn('id', $forcedPosts)
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $language_code);

        $forcedPostsCollection = $query->get()
            ->sortBy(function($post) use ($forcedPosts) {
                return array_search($post->id, $forcedPosts->toArray());
            });
        
        if ($forcedPostsCollection->count() < $limit) {
            $additionalQuery = Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->whereNotIn('id', $forcedPosts);
            
            if (is_array($post_types) && !empty($post_types)) {
                $additionalQuery->whereIn('type', $post_types);
            } elseif ($post_types) {
                $additionalQuery->where('type', $post_types);
            }

            $additionalPosts = $additionalQuery
                ->orderBy($sort_by, 'desc')
                ->limit($limit - $forcedPostsCollection->count())
                ->get();
            
            $posts = $forcedPostsCollection->concat($additionalPosts);
        } else {
            $posts = $forcedPostsCollection->take($limit);
        }
        
        return PostResource::collection($posts);
    }

    private function getMainInvestigation($language_code)
    {
        $mainInvestigation = InvestigationTheme::where('language_code', $language_code)->where('is_main', true)->first();

        if (!$mainInvestigation) {
            return null;
        }

        return [
            'title' => $mainInvestigation->title,
            'path' => $mainInvestigation->getPath(),
            'posts' => PostResource::collection($mainInvestigation->posts)->toArray(request()),
        ];
    }

    private function getConfession($language_code)
    {
        return PostResource::collection(
            Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('category_id', Category::getConfessionCategoryId($language_code))
                ->orderBy('published_at', 'desc')
                ->simplePaginate(36)
        )->toArray(request());
    }

    private function getArticles($language_code)
    {
        return PostResource::collection(
            Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->whereNotIn('type', [PostTypes::OPINION, PostTypes::NEWS])
                ->whereNotIn('id', $this->excludedIds)
                ->where('category_id', '!=', 12)
                ->orderBy('published_at', 'DESC')
                ->simplePaginate(36)
        )->toArray(request());
    }

    private function getPopular($language_code)
    {
        return Cache::remember(
            "main_page:popular:{$language_code}",
            now()->addSeconds(self::HOT_BLOCK_CACHE_TTL_SECONDS),
            function () use ($language_code) {
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
                        ->where('published_at', '>=', now()->subMonth())
                        ->whereNotIn('id', $forcedPosts)
                        ->with(['category', 'authors', 'columnist'])
                        ->orderBy('views_count', 'desc')
                        ->limit($limit - $forcedPostsCollection->count())
                        ->get();
                    
                    $posts = $forcedPostsCollection->concat($additionalPosts);
                } else {
                    $posts = $forcedPostsCollection->take($limit);
                }
                
                return PostResource::collection($posts)->toArray(request());
            }
        );
    }
}