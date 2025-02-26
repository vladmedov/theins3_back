<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Laravel\Nova\NovaServiceProvider;

class CompactFlexibleCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        $content = json_decode($value, true);

        if (empty($content)) {
            return [];
        }

        if ($this->isNovaRequest()) {
            return $this->getExpandedContent($content);
        }

        return $content;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return static::convertToDbFormat($value);
        // if (is_string($value)) {
        //     $value = json_decode($value, true);
        // }

        // $formattedBlock = [];

        // foreach ($value as $block) {
        //     if (empty($block['key']) || empty($block['layout']) || empty($block['attributes'])) {
        //         continue;
        //     }

        //     $key = $block['key'];
        //     $layout = $block['layout'];
        //     $attributes = $block['attributes'];

        //     $formattedBlock[$key] = [
        //         'type' => $layout,
        //         'attributes' => $attributes
        //     ];
        // }

        // return json_encode($formattedBlock);
    }

    protected function getExpandedContent($content)
    {
        $expanded = [];

        foreach ($content as $key => $block) {
            $layout = $block['type'];
            $attributes = $block['attributes'];

            $expanded[] = [
                'layout' => $layout,
                'key' => $key,
                'attributes' => $attributes
            ];
        }

        return $expanded;
    }

    protected function isNovaRequest()
    {
        return app()->getProvider(NovaServiceProvider::class) 
            && !app()->runningInConsole()
            && request()->is('nova-api/*');
    }

    public static function convertToDbFormat($value) {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $formattedBlock = [];

        foreach ($value as $block) {
            if (empty($block['key']) || empty($block['layout']) || empty($block['attributes'])) {
                continue;
            }

            $key = $block['key'];
            $layout = $block['layout'];
            $attributes = $block['attributes'];

            $formattedBlock[$key] = [
                'type' => $layout,
                'attributes' => $attributes
            ];
        }

        return json_encode($formattedBlock);
    }
}
