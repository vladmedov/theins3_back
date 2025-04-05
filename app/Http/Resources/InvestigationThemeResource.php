<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestigationThemeResource extends JsonResource
{
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
        return [
            'id' => $this->id,
            'path' => $this->getPath(),
            'title' => $this->title,
            'image' => $this->image ?? "",
            'posts_count' => $this->posts->count(),
        ]
        + $this->when($this->inList, function () {
            return [];
        }, function () use ($request) {
            return ['posts' => (PostResource::collection($this->posts))->toArray($request)];
        });
    }

    private function getPath()
    {
        return '/'
            . ($this->language_code === 'ru' ? 'investigations/' : "{$this->language_code}/investigations/")
            . "{$this->slug}";
    }
}
