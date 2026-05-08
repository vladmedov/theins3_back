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
            'image_width' => $this->image_width !== null ? (int) $this->image_width : null,
            'image_height' => $this->image_height !== null ? (int) $this->image_height : null,
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
            $blocks[$key] = $this->transformPublicBlock($block);
        }

        return app(TerminSpanPublicTransformer::class)->transformContentBlocks($blocks);
    }

    /**
     * Public-shape transformations for a single block: image link → /storage/... URL,
     * related posts → loaded resources. Recurses into accordion items' nested blocks
     * so images / related referenced via insertion-code inside an accordion item also
     * get the same treatment.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function transformPublicBlock(array $block): array
    {
        $type = $block['type'] ?? '';

        if ($type === 'related') {
            $ids = $block['attributes']['related_posts'] ?? [];
            if (is_array($ids) && $ids !== []) {
                $posts = PostResource::collection(
                    Post::whereIn('id', $ids)->where('status', Post::STATUS_PUBLISHED)->get()
                );
                $block['attributes']['related_posts'] = $posts;
            }
        }

        if (in_array($type, ['images', 'online'], true) && !empty($block['attributes']['images'])) {
            $images = $block['attributes']['images'];
            if (isset($images['link'])) {
                $images = [$images];
            }
            foreach ($images as $i => $image) {
                $imageId = $image['id'] ?? null;
                $link = $image['link'] ?? null;
                if ($link && $imageId) {
                    $imageType = $type === 'online'
                        ? ImageService::TYPE_ONLINE_IMAGE
                        : ImageService::TYPE_CONTENT_IMAGE;
                    $images[$i]['link'] = ImageService::getImageUrl(
                        $imageId, $link, $imageType, ImageService::SIZE_ORIGINAL, false
                    );
                }
                if (isset($image['width']) && is_numeric($image['width'])) {
                    $images[$i]['width'] = (int) $image['width'];
                }
                if (isset($image['height']) && is_numeric($image['height'])) {
                    $images[$i]['height'] = (int) $image['height'];
                }
            }
            $block['attributes']['images'] = $images;
        }

        if ($type === 'accordion' && !empty($block['attributes']['items'])) {
            $items = $block['attributes']['items'];
            if (is_array($items)) {
                foreach ($items as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $itemBlocks = $item['blocks'] ?? null;
                    if (! is_array($itemBlocks)) {
                        continue;
                    }
                    foreach ($itemBlocks as $j => $sub) {
                        if (is_array($sub)) {
                            $itemBlocks[$j] = $this->transformPublicBlock($sub);
                        }
                    }
                    $items[$i]['blocks'] = $itemBlocks;
                }
                $block['attributes']['items'] = $items;
            }
        }

        return $block;
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
