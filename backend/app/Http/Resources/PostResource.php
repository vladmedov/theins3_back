<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Enums\PostTypes;

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
            'count_views' => $this->count_views ?? 0,
            'tags' => $this->whenLoaded('tags', function () use ($request) {
                return (TagResource::collection($this->tags))->toArray($request);
            }), 
            'investigation_theme' => $this->whenLoaded('investigationTheme', function () use ($request) {
                return (new InvestigationThemeResource($this->investigationTheme))->toArray($request);
            }),
            'translation' => $this->whenLoaded('translation', function () use ($request) {
                return (new PostResource($this->translation))->toArray($request);
            }),
            'lead' => $this->lead ?? "",
            'content' => $this->content,
            'termins' => $this->whenLoaded('termins', function () use ($request) {
                return (TerminResource::collection($this->termins))->toArray($request);
            }),
            'seo_title' => $this->seo_title ?? "",
            'seo_description' => $this->seo_description ?? "",
            'seo_keywords' => $this->seo_keywords ?? "",
            'widgets' => $this->getWidgets(),
        ]);
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

    private function getPath()
    {
        return '/'
            . ($this->language_code === 'ru' ? "{$this->category->slug}/" : "{$this->language_code}/{$this->category->slug}/")
            . "{$this->slug}";
    }
}