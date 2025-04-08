<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Post;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    public function getPost($language_code, $category_slug, $slug)
    {
        $post = Post
            ::with([
                'category',
                'investigationTheme',
                'translation',
                'translation.category',
                'translation.authors',
                'translation.columnist',
                'tags',
                'termins',
                'authors',
                'columnist',
            ])
            ->where('slug', $slug)
            ->where('language_code', $language_code)
            ->where('status', Post::STATUS_PUBLISHED)
            ->firstOrFail();

        if ($post->category->slug !== $category_slug) {
            return response()->json(['redirect' => $post->getPath()], 301);
        }

        return new PostResource($post, false);
    }

    public function getAllExceptOpinions($language_code = 'ru')
    {
        $posts = Post
            ::where('language_code', $language_code)
            ->where('status', Post::STATUS_PUBLISHED)
            ->whereNot('type', PostTypes::OPINION)
            ->orderBy('published_at', 'desc')
            ->simplePaginate(36);
            
        return PostResource::collection($posts)->toArray(request());
    }





    public function getBySlug($slug)
    {
        $post = Post::with([
            'category',
            'investigationTheme',
            'translation',
            'translation.category',
            'translation.authors',
            'translation.columnist',
            'tags',
            'termins',
            'authors',
            'columnist',
        ])->where('slug', $slug)->firstOrFail();

        //dd((new PostResource($post))->toArray(request()));

        $resource = new PostResource($post);
        return $resource->toArray(request());    
    }

    public function getByCategorySlug($slug)
    {
        return $this->getPostsByRelation('category', $slug);
    }

    public function getByTagSlug($slug)
    {
        return $this->getPostsByRelation('tags', $slug);
    }

    public function getByInvestigationThemeSlug($slug)
    {
        return $this->getPostsByRelation('investigationTheme', $slug);
    }

    public function getByColumnistSlug($slug)
    {
        dd(array_merge($this->getPostsByRelation('columnist', $slug), [
            'top_columnists' => \App\Http\Resources\ColumnistResource::collection(\App\Models\User::getColumnistsWithMinPosts(1))
            ->toArray(request())
        ]));
        
        //return $this->getPostsByRelation('columnist', $slug, $additionalData);
    }

    public function getByAuthorSlug($slug) 
    {
        return $this->getPostsByRelation('authors', $slug);
    }

    private function getPostsByRelation($relation, $slug)
    {
        $entityModel = $this->getEntityModelByRelation($relation);
        $entity = $entityModel->where('slug', $slug)
            ->firstOrFail();
        
        $entityResource = $this->getEntityResource($relation, $entity)->toArray(request());

        //dd($entityResource);

        return $entityResource;
    }

    private function getEntityModelByRelation($relation)
    {
        $models = [
            'category' => \App\Models\Category::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist']);
            }])->where('language_code', app()->getLocale()),
            'tags' => \App\Models\Tag::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist']);
            }])->where('language_code', app()->getLocale()),
            'investigationTheme' => \App\Models\InvestigationTheme::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist']);
            }])->where('language_code', app()->getLocale()),
            'columnist' => \App\Models\User::with(['opinions' => function($query) {
                $query->with(['category', 'authors', 'columnist']);
            }]),
            'authors' => \App\Models\User::with(['notOpinions' => function($query) {
                $query->with(['category', 'authors', 'columnist']);
            }]),
        ];
        
        return $models[$relation] ?? null;
    }

    private function getEntityResource($relation, $entity)
    {
        $resources = [
            'category' => \App\Http\Resources\CategoryResource::class,
            'tags' => \App\Http\Resources\TagResource::class,
            'investigationTheme' => \App\Http\Resources\InvestigationThemeResource::class,
            'columnist' => \App\Http\Resources\ColumnistResource::class,
            'authors' => \App\Http\Resources\AuthorResource::class,
        ];
        
        $resourceClass = $resources[$relation] ?? null;
        
        if (!$resourceClass) {
            return $entity;
        }
        
        return new $resourceClass($entity);
    }
}