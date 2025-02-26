<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Enums\PostTypes;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'path' => $this->generatePath(),
            'type' => $this->type,
            'published_at' => $this->published_at,
            'category' => $this->whenLoaded('category', function () use ($request) {
                return (new CategoryCardResource($this->category))->toArray($request);
            }),
            'title' => $this->title,
            'lead' => $this->lead,
            'content' => $this->content,
            'image' => $this->image,
            'image_description' => $this->image_description,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'investigation_theme' => $this->whenLoaded('investigationTheme', function () use ($request) {
                return (new InvestigationThemeResource($this->investigationTheme))->toArray($request);
            }),
            'translation' => $this->whenLoaded('translation', function () use ($request) {
                return (new PostCardResource($this->translation))->toArray($request);
            }),
            'tags' => $this->whenLoaded('tags', function () use ($request) {
                return (new TagCollection($this->tags))->toArray($request);
            }),
            'termins' => $this->whenLoaded('termins', function () use ($request) {
                return (new TerminCollection($this->termins))->toArray($request);
            }),
            'columnist' => $this->whenLoaded('columnist', function () use ($request) {
                return (new ColumnistCardResource($this->columnist))->toArray($request);
            }),
            'authors' => $this->whenLoaded('authors', function () use ($request) {
                return (new AuthorCardCollection(
                    $this->authors->filter(fn($author) => !$author->hide_author_name_in_all_news || $this->author_visibility === 'force_shown')
                ))->toArray($request);
            }),
        ];
    }

    private function generatePath(): string 
    {
        return '/'
            . ($this->language_code === 'ru' ? '' : "{$this->language_code}/")
            . "{$this->category->slug}/{$this->slug}";
    }

    private function shouldShowColumnist(): bool
    {
        if ($this->type !== PostTypes::OPINION) {
            return false;
        }

        if ($this->author_visibility === 'force_hidden') {
            return false;
        }

        if ($this->columnist->hide_author_name_in_all_news && $this->author_visibility !== 'force_shown') {
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
}