<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TagCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->transform(function($tag) use ($request) {
            return (new TagResource($tag))->toArray($request);
        })->toArray($request);
    }
}
