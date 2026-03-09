<?php

namespace App\Traits;

use App\Http\Controllers\WidgetController;

trait HasWidgets
{
    private function getWidgets(): array
    {
        return [];
    }
    
    protected function getTopNewsWidget($language_code): array
    {
        $controller = new WidgetController();
        return [
            'type' => 'top_news',
            'attributes' => [
                'posts' => $controller->getTopNews($language_code)->toArray(request())
            ]
        ];
    }
    
    protected function getPopularWidget($language_code): array
    {
        $controller = new WidgetController();
        return [
            'type' => 'popular',
            'attributes' => [
                'posts' => $controller->getPopular($language_code)->toArray(request())
            ]
        ];
    }
    
    protected function getOpinionsWidget($language_code): array
    {
        $controller = new WidgetController();
        return [
            'type' => 'opinions',
            'attributes' => [
                'posts' => $controller->getOpinions($language_code)->toArray(request())
            ]
        ];
    }
    
    protected function getColumnistsWidget($language_code): array
    {
        $controller = new WidgetController();
        return [
            'type' => 'columnists',
            'attributes' => [
                'users' => $controller->getColumnists($language_code)->toArray(request())
            ]
        ];
    }
    
    protected function getSocialWidget(): array
    {
        return ['type' => 'social', 'attributes' => []];
    }
    
    protected function getSubscribeWidget(): array
    {
        return ['type' => 'subscribe', 'attributes' => []];
    }
    
    protected function getDonateWidget($probability = 100): array
    {
        if (rand(1, 100) > $probability) {
            return ['type' => 'donate_hidden', 'attributes' => []];
        }
        return ['type' => 'donate', 'attributes' => []];
    }
    
    protected function getVpnWidget($probability = 100): array
    {
        if (rand(1, 100) > $probability) {
            return ['type' => 'vpn_hidden', 'attributes' => []];
        }
        return ['type' => 'vpn', 'attributes' => []];
    }
    
    protected function getRandomWidget($language_code, $widgetTypes = ['top_news', 'opinions']): array
    {
        $randomType = $widgetTypes[array_rand($widgetTypes)];
        
        switch ($randomType) {
            case 'top_news':
                return $this->getTopNewsWidget($language_code);
            case 'opinions':
                return $this->getOpinionsWidget($language_code);
            case 'subscribe':
                return $this->getSubscribeWidget();
            case 'columnists':
                return $this->getColumnistsWidget($language_code);
            case 'popular':
                return $this->getPopularWidget($language_code);
            case 'social':
                return $this->getSocialWidget();
            case 'donate':
                return $this->getDonateWidget();
            case 'vpn':
                return $this->getVpnWidget();
            default:
                return $this->getSubscribeWidget();
        }
    }
}
