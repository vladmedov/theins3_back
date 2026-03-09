<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Traits\HasWidgets;

class CategoryResource extends JsonResource
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
            'type' => $this->type,
            'path' => $this->getPath(),
            'title' => $this->title,
        ]
        + $this->when($this->inList, function () {
            return [];
        }, function () use ($request) {
            return ['posts' => (PostResource::collection($this->posts))->toArray($request)];
        })
        + $this->when($this->inList, function () {
            return [];
        }, function () {
            return ['widgets' => $this->getWidgets()];
        });
    }

    private function getWidgets(): array
    {
        $widgets = [];
        $isRu = $this->language_code === 'ru';

        if ($this->type === 'news') {
            $widgets = [
                $this->getSubscribeWidget(),
                $this->getRandomWidget($this->language_code, $isRu ? ['opinions', 'popular', 'donate', 'vpn'] : ['opinions', 'popular', 'donate']),
                $this->getSocialWidget(),
            ];
        } elseif ($this->type === 'opinion') {
            $widgets = [
                $this->getSubscribeWidget(),
                $this->getRandomWidget($this->language_code, $isRu ? ['columnists', 'donate', 'vpn'] : ['columnists', 'donate']),
                $this->getSocialWidget(),
            ];
        } else {
            $widgets = [
                $this->getSubscribeWidget(),
                $this->getPopularWidget($this->language_code),
                $this->getRandomWidget($this->language_code, $isRu ? ['top_news', 'opinions', 'donate', 'vpn'] : ['top_news', 'opinions', 'donate']),
                $this->getSocialWidget(),
            ];
        }
        
        $widgets[] = $this->getPopularWidget($this->language_code);
        
        return $widgets;
    }

    private function getPath()
    {
        return '/'
            . ($this->language_code === 'ru' ? '' : "{$this->language_code}/")
            . "{$this->slug}";
    }
}
