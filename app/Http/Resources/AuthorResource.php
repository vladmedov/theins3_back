<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Traits\HasWidgets;

class AuthorResource extends JsonResource
{
    use HasWidgets;

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
            'fullname' => $this->fullname,
            'avatar' => $this->avatar_url ?? "",
            'description' => $this->description ?? "",
            'position' => $this->position ?? "",
            'twitter' => $this->twitter ?? "",
            'facebook' => $this->facebook ?? "",
        ]
        + $this->when($this->inList, function () {
            return [];
        }, function () use ($request) {
            return ['posts' => (PostResource::collection($this->notOpinions))->toArray($request)];
        });
    }

    private function getPath()
    {
        return '/'
            . ($this->language_code === 'ru' ? 'authors/' : "{$this->language_code}/authors/")
            . "{$this->slug}";
    }
}
