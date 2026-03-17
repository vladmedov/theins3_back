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

            // Computed insertion code for Nova (not stored)
            $attributes['_insertion_code'] = '{{ ' . $layout . '_id' . $key . ' }}';

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
            // key может быть 0 (первый блок) — empty(0) в PHP true, поэтому не используем empty()
            if (!array_key_exists('key', $block) || $block['key'] === '' || $block['key'] === null
                || empty($block['layout']) || empty($block['attributes'])) {
                continue;
            }

            $key = $block['key'];
            $layout = $block['layout'];
            $attributes = $block['attributes'];

            // Do not persist computed/UI-only attributes (e.g. _insertion_code)
            $attributes = array_filter(
                $attributes,
                fn ($attrKey) => !is_string($attrKey) || strpos($attrKey, '_') !== 0,
                ARRAY_FILTER_USE_KEY
            );

            $formattedBlock[$key] = [
                'type' => $layout,
                'attributes' => $attributes
            ];
        }

        return json_encode($formattedBlock);
    }
}
