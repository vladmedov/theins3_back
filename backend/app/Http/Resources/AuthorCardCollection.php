<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AuthorCardCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->transform(function($author) use ($request) {
            return (new AuthorCardResource($author))->toArray($request);
        })->toArray($request);
    }
}