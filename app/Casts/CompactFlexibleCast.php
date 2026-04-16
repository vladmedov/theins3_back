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

            if (in_array($layout, ['images', 'online'], true)) {
                $attributes = static::normalizeImageDimensions($attributes);
            }

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

    /**
     * Normalize width/height in images payload to integers.
     */
    private static function normalizeImageDimensions(array $attributes): array
    {
        $images = $attributes['images'] ?? null;
        if (!is_array($images)) {
            return $attributes;
        }

        if (!array_is_list($images)) {
            return $attributes;
        }

        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                continue;
            }
            $images[$index] = static::normalizeSingleImageDimensions($image);
        }

        $attributes['images'] = $images;

        return $attributes;
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>
     */
    private static function normalizeSingleImageDimensions(array $image): array
    {
        if (array_key_exists('width', $image) && is_numeric($image['width'])) {
            $image['width'] = (int) $image['width'];
        }
        if (array_key_exists('height', $image) && is_numeric($image['height'])) {
            $image['height'] = (int) $image['height'];
        }

        return $image;
    }
}
