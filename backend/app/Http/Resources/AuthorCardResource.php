<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\PostTypes;

class AuthorCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'path' => $this->generatePath(),
            'fullname' => $this->fullname,
            'position' => $this->pivot->position,
        ];
    }

    private function generatePath(): string 
    {
        return "/{$this->slug}";
    }
}
