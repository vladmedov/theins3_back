<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'termin' => $this->termin,
            'description' => $this->description,
        ];
    }
}
