<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Post;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;
use App\Http\Resources\CategoryResource;

use App\Models\Category;
use App\Models\CollectionPost;
use App\Models\InvestigationTheme;

class MainPageController extends Controller
{
    private $excludedIds = [];

    public function getLayoutData($language_code)
    {
        return [
            'rates' => $this->getRates(),
            'categories' => $this->getCategories($language_code),
        ];
    }

    public function getMainPage($language_code)
    {
        $feature = $this->getCollectionPosts($language_code, CollectionPost::COLLECTION_CODE_FEATURE, 3);
        $this->excludedIds = $feature->pluck('id');

        return [
            'collection_opinions' => $this->getCollectionPosts($language_code, CollectionPost::COLLECTION_CODE_MAIN_OPINIONS, 7, PostTypes::OPINION),
            'collection_feature' => $feature,
            'collection_popular' => $this->getCollectionPosts($language_code, CollectionPost::COLLECTION_CODE_POPULAR, 5, null, 'views_count'),
            'main_investigation' => $this->getMainInvestigation($language_code),
            'confessions' => $this->getConfession($language_code),
            'news' => $this->getNews($language_code),
            'articles' => $this->getArticles($language_code),
        ];
    }

    private function getRates()
    {
        return [
            [
                'currency' => 'USD',
                'value' => rand(8020, 8050) / 100,
                'dynamics' => rand(0, 100) > 70,
            ],
            [
                'currency' => 'EUR',
                'value' => rand(9050, 9099) / 100,
                'dynamics' => rand(0, 100) > 70,
            ],
            [
                'currency' => 'OIL',
                'value' => rand(7000, 7099) / 100,
                'dynamics' => rand(0, 100) > 30,
            ],
        ];
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

        $firstCategory = [$categories[1]];
        unset($categories[1]);
        
        if ($language_code === 'ru') {
            $militaryTag = [
                [
                    'type' => 'custom',
                    'path' => '/tags/vs',
                    'title' => 'Военная сводка',
                ]
            ];
            $categories = array_merge($firstCategory, $militaryTag, $categories);
        } else {
            $categories = array_merge($firstCategory, $categories);
        }

        return $categories;
    }

    private function getNews($language_code)
    {
        return PostResource::collection(
            Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('type', PostTypes::NEWS)
                ->orderBy('published_at', 'desc')
                ->limit(10)
                ->get()
        );
    }

    private function getCollectionPosts($language_code, $collection_code, $limit, $post_type = null, $sort_by = 'published_at')
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
        
        if ($post_type) {
            $query->where('type', $post_type);
        }

        $forcedPostsCollection = $query->get()
            ->sortBy(function($post) use ($forcedPosts) {
                return array_search($post->id, $forcedPosts->toArray());
            });
        
        if ($forcedPostsCollection->count() < $limit) {
            $additionalQuery = Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->whereNotIn('id', $forcedPosts);
            
            if ($post_type) {
                $additionalQuery->where('type', $post_type);
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
            'posts' => PostResource::collection($mainInvestigation->posts)->toArray(request()),
        ];
    }

    private function getConfession($language_code)
    {
        return PostResource::collection(
            Post
                ::where('language_code', $language_code)
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('category_id', Category::CATEGORY_ID_CONFESSION)
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
                ->orderBy('published_at', 'DESC')
                ->simplePaginate(36)
        )->toArray(request());
    }
}