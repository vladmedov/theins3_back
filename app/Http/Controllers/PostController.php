<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Post;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;
use App\Services\PostPreviewTokenService;

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

    /**
     * Exchange one-time preview_code for an access_token.
     */
    public function exchangePostPreview($language_code, PostPreviewTokenService $previewTokens)
    {
        $code = request()->query('code');
        if (!$code) {
            abort(404);
        }

        $exchanged = $previewTokens->exchangeCode($code);
        if (!$exchanged) {
            abort(404);
        }

        $post = Post::with('category')->find($exchanged['post_id']);
        if (!$post || $post->language_code !== $language_code || $post->status !== Post::STATUS_DRAFT) {
            abort(404);
        }

        return response()
            ->json([
                'ok' => true,
                'access_token' => $exchanged['token'],
                'path' => $post->getPath(),
                'expires_in' => $exchanged['expires_in'],
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Preview a draft post via X-Post-Preview-Token header.
     * Same lookup as getPost, but draft-only and gated by token post_id.
     */
    public function getPostPreview($language_code, $category_slug, $slug, PostPreviewTokenService $previewTokens)
    {
        $token = request()->header('X-Post-Preview-Token');
        if (!$token) {
            abort(404);
        }

        $tokenPostId = $previewTokens->validateAccessToken($token);
        if (!$tokenPostId) {
            abort(404);
        }

        $post = Post
            ::with([
                'category',
                'investigationTheme',
                'translation',
                'translation.category',
                'translation.authors',
                'translation.columnist',
                'tags',
                'authors',
                'columnist',
            ])
            ->where('slug', $slug)
            ->where('language_code', $language_code)
            ->where('status', Post::STATUS_DRAFT)
            ->firstOrFail();

        if ((int) $post->id !== (int) $tokenPostId) {
            abort(404);
        }

        return (new PostResource($post, false))
            ->response()
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
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
            'authors',
            'columnist',
        ])->where('slug', $slug)->where('status', Post::STATUS_PUBLISHED)->firstOrFail();

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
