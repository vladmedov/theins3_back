<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'path' => $this->generatePath(),
            'title' => $this->title,
        ];
    }

    private function generatePath(): string 
    {
        return '/'
            . ($this->language_code === 'ru' ? '' : "{$this->language_code}/")
            . "tags/{$this->slug}";
    }
}
