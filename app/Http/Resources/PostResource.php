<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Enums\PostTypes;
use App\Models\Post;
use App\Services\ContentInsertionCodeService;
use App\Services\ImageService;
use App\Services\TerminSpanPublicTransformer;

use App\Traits\HasWidgets;

class PostResource extends JsonResource
{
    use HasWidgets;

    private $inList = true;

    public function __construct($resource, $inList = true)
    {
        parent::__construct($resource);
        if ($inList === false) {
            $this->inList = false;
        }
    }

    public function toArray(Request $request): array
    {
        $language_code = $this->language_code;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'path' => $this->getPath(),
            'published_at' => $this->published_at,
            'category' => $this->whenLoaded('category', function () use ($request) {
                return (new CategoryResource($this->category))->toArray($request);
            }),
            'title' => $this->title,
            'image' => $this->image_url ?? "",
            'image_description' => $this->image_description ?? "",
            'is_super_news' => $this->is_super_news,
            'language_code' => $this->language_code,
        ]
        + ($this->shouldShowColumnist() ? $this->whenLoaded('columnist', function () use ($request, $language_code) {
            return ['columnist' => (new ColumnistResource($this->columnist))->toArray($request)];
        }, []) : [])
        + ($this->shouldShowAuthors() ? $this->whenLoaded('authors', function () use ($request) {
            return ['authors' => (AuthorResource::collection(
                $this->authors->filter(fn($author) => !in_array($this->type, $author->post_types_with_hidden_author_name) || $this->author_visibility === 'force_shown')
            ))->toArray($request)];
        }, []) : [])
        + ($this->inList ? [] : [
            'views_count' => $this->views_count ?? 0,
            'tags' => $this->whenLoaded('tags', function () use ($request) {
                return (TagResource::collection($this->tags))->toArray($request);
            }), 
            'investigation_theme' => $this->whenLoaded('investigationTheme', function () use ($request) {
                return (new InvestigationThemeResource($this->investigationTheme))->toArray($request);
            }),
            'translation' => $this->whenLoaded('translation', function () use ($request) {
                if (!$this->translation || $this->translation->status !== 'published') {
                    return null;
                }
                return (new PostResource($this->translation))->toArray($request);
            }),
            'lead' => $this->lead ?? "",
            'content' => $this->getBlocks(),
            'seo_title' => $this->seo_title ?? "",
            'seo_description' => $this->seo_description ?? "",
            'seo_keywords' => $this->seo_keywords ?? "",
            'share_image' => \App\Services\ShareImageService::getShareImageUrl($this->resource) ?? "",
            'widgets' => $this->getWidgets(),
        ]);
    }

    private function getBlocks()
    {
        $blocks = app(ContentInsertionCodeService::class)->expand($this->content);

        foreach ($blocks as $key => $block) {
            if ($block['type'] === 'related') {
                $ids = $block['attributes']['related_posts'];
                $posts = PostResource::collection(Post::whereIn('id', $ids)->where('status', Post::STATUS_PUBLISHED)->get());
                $block['attributes']['related_posts'] = $posts;
                $blocks[$key] = $block;
            }

            if (in_array($block['type'], ['images', 'online']) && !empty($block['attributes']['images'])) {
                $images = $block['attributes']['images'];
                if (isset($images['link'])) {
                    $images = [$images];
                }
                foreach ($images as $i => $image) {
                    $imageId = $image['id'] ?? null;
                    $link = $image['link'] ?? null;
                    if ($link && $imageId) {
                        $imageType = $block['type'] === 'online'
                            ? ImageService::TYPE_ONLINE_IMAGE
                            : ImageService::TYPE_CONTENT_IMAGE;
                        $images[$i]['link'] = ImageService::getImageUrl(
                            $imageId, $link, $imageType, ImageService::SIZE_ORIGINAL, false
                        );
                    }
                }
                $block['attributes']['images'] = $images;
                $blocks[$key] = $block;
            }
        }
        return app(TerminSpanPublicTransformer::class)->transformContentBlocks($blocks);
    }

    private function shouldShowColumnist(): bool
    {
        if ($this->columnist === null) { // TODO: check
            return false;
        }

        if ($this->type !== PostTypes::OPINION) {
            return false;
        }

        if ($this->author_visibility === 'force_hidden') {
            return false;
        }

        if (in_array($this->type, $this->columnist->post_types_with_hidden_author_name) && $this->author_visibility !== 'force_shown') {
            return false;
        }

        return true;
    }

    private function shouldShowAuthors(): bool
    {
        if ($this->type === PostTypes::OPINION) {
            return false;
        }

        if ($this->author_visibility === 'force_hidden') {
            return false;
        }

        return true;
    }

    private function getWidgets(): array
    {
        return [
            $this->getPopularWidget($this->language_code),
            $this->getSubscribeWidget(),
        ];
    }

    private function getPath()
    {
        $columnistPrefix = ($this->type === PostTypes::OPINION && $this->columnist)
            ? "{$this->columnist->slug}/"
            : '';

        return '/'
            . ($this->language_code === 'ru' ? "{$this->category->slug}/" : "{$this->language_code}/{$this->category->slug}/")
            . $columnistPrefix
            . "{$this->slug}";
    }
}
