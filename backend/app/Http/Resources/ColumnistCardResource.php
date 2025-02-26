<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\PostTypes;

class ColumnistCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'path' => $this->generatePath(),
            'fullname' => $this->fullname,
        ];
    }

    private function generatePath(): string 
    {
        return "/{$this->slug}";
    }
}
