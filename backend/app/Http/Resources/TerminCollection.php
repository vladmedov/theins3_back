<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TerminCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->transform(function($termin) use ($request) {
            return (new TerminResource($termin))->toArray($request);
        })->toArray($request);
    }
}
