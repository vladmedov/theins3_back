<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\PostTypes;

class PostCardResource extends JsonResource
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
            'image' => $this->image,
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
