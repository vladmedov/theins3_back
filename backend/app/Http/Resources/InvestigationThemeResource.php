<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\PostTypes;

class InvestigationThemeResource extends JsonResource
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
            . "{$this->slug}";
    }
}
