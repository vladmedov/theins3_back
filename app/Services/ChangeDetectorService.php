<?php

namespace App\Services;

use Corbon\Corbon;

class ChangeDetectorService
{
    public static function compare(array $old, array $new, array $excludedFields)
    {
        $changes = [];

        $allFields = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allFields as $field) {
            if (in_array($field, $excludedFields)) {
                continue;
            }

            if ($field === 'content') {
                $change = static::compareContent($old[$field] ?? "[]", $new[$field] ?? "[]");
            } else {
                $change = static::compareField($old[$field] ?? null, $new[$field] ?? null);
            }

            if (!empty($change)) {
                $changes[$field] = $change;
            }
        }

        return $changes;
    }

    public static function compareField($old, $new)
    {
        if (md5(static::toString($old)) === md5(static::toString($new))) {
            return null;
        }        

        return [
            'old' => static::toString($old),
            'new' => static::toString($new)
        ];
    }

    public static function compareContent($old, $new)
    {
        if (md5($old) === md5($new)) {
            return null;
        }

        $oldContent = is_string($old) ? json_decode($old, true) : $old;
        $newContent = is_string($new) ? json_decode($new, true) : $new;

        $changes = [];

        $allBlocks = array_unique(array_merge(array_keys($oldContent), array_keys($newContent)));

        foreach ($allBlocks as $blockID) {
            $oldBlock = $oldContent[$blockID] ?? null;
            $newBlock = $newContent[$blockID] ?? null;

            $type = $newBlock['type'] ?? $oldBlock['type'] ?? 'unknown';

            if ($type === 'gallery') {
                $oldGallery = $oldBlock['attributes']['gallery'] ?? null;
                $newGallery = $newBlock['attributes']['gallery'] ?? null;
                $change = static::compareField($oldGallery, $newGallery);
            } else {
                $oldAttributes = $oldBlock['attributes'] ?? null;
                $newAttributes = $newBlock['attributes'] ?? null;
                $change = static::compareField($oldAttributes, $newAttributes);
            }

            if ($change) {
                $changes[$blockID] = [
                    'type' => $type,
                    'old' => $change['old'],
                    'new' => $change['new'],
                ];
            }
        }

        return empty($changes) ? null : ['changes' => $changes];
    }

    public static function toString($value)
    {
        if ($value instanceof \Datetime) {
            return $value->format('Y-m-d\TH:i:s.u\Z');
        } elseif (is_array($value)) {
            return json_encode(array_map([self::class, 'toString'], $value));
        } elseif (is_object($value)) {
            return json_encode(array_map([self::class, 'toString'], (array) $value));
        } else {
            return (string) $value;
        }
    }
}
