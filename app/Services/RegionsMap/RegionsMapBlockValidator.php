<?php

namespace App\Services\RegionsMap;

use App\Enums\RegionsMapColor;
use App\Support\RegionsMap\RegionsMapRegions;
use App\Support\RegionsMap\RegionsMapSchemas;

class RegionsMapBlockValidator
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string> field => translated message
     */
    public function validate(array $attributes): array
    {
        $errors = [];

        if (array_key_exists('title', $attributes) && $attributes['title'] !== null && $attributes['title'] !== '') {
            if (! is_string($attributes['title'])) {
                $errors['regions_map.title'] = __('validation.regions_map.title.string');
            } elseif (mb_strlen($attributes['title']) > RegionsMapRegions::MAX_TITLE_LENGTH) {
                $errors['regions_map.title'] = __('validation.regions_map.title.max', [
                    'max' => RegionsMapRegions::MAX_TITLE_LENGTH,
                ]);
            }
        }

        $errors = array_merge($errors, $this->validateSchema($attributes['schema'] ?? null));

        $schema = RegionsMapSchemas::resolve($attributes['schema'] ?? null);

        $errors = array_merge($errors, $this->validateComment($attributes['comment'] ?? null));
        $errors = array_merge($errors, $this->validateColorLabels($attributes['color_labels'] ?? null));
        $errors = array_merge($errors, $this->validateRegions($attributes['regions'] ?? null, $schema));

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function validateSchemaImmutable(?string $oldSchema, ?string $newSchema): array
    {
        if ($oldSchema === null || $oldSchema === '') {
            return [];
        }

        $resolvedOld = RegionsMapSchemas::resolve($oldSchema);
        $resolvedNew = RegionsMapSchemas::resolve($newSchema);

        if ($resolvedOld !== $resolvedNew) {
            return ['regions_map.schema' => __('validation.regions_map.schema.locked')];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalize(array $attributes): array
    {
        $normalized = [
            'schema' => RegionsMapSchemas::resolve($attributes['schema'] ?? null),
        ];

        if (array_key_exists('title', $attributes) && is_string($attributes['title'])) {
            $title = trim($attributes['title']);
            if ($title !== '') {
                $normalized['title'] = $title;
            }
        }

        if (array_key_exists('comment', $attributes)) {
            $comment = $this->normalizeComment($attributes['comment']);
            if ($comment !== null) {
                $normalized['comment'] = $comment;
            }
        }

        $colorLabels = $attributes['color_labels'] ?? RegionsMapRegions::defaultColorLabels();
        if (is_array($colorLabels)) {
            $normalized['color_labels'] = $this->normalizeColorLabels($colorLabels);
        }

        $regions = $attributes['regions'] ?? [];
        if (is_array($regions)) {
            $normalized['regions'] = $this->normalizeRegions($regions, $normalized['schema']);
        }

        return $normalized;
    }

    /**
     * Unpack Nova editor bundle into API attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function unpackEditorAttributes(array $attributes): array
    {
        if (! isset($attributes['regions_map_editor'])) {
            return $attributes;
        }

        $editor = $attributes['regions_map_editor'];
        if (is_string($editor)) {
            $editor = json_decode($editor, true) ?? [];
        }

        if (! is_array($editor)) {
            unset($attributes['regions_map_editor']);

            return $attributes;
        }

        unset($attributes['regions_map_editor']);

        foreach (['schema', 'title', 'comment', 'color_labels', 'regions'] as $key) {
            if (array_key_exists($key, $editor)) {
                $attributes[$key] = $editor[$key];
            }
        }

        return $attributes;
    }

    /**
     * Pack API attributes for Nova editor field.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function packEditorAttributes(array $attributes): array
    {
        $schema = RegionsMapSchemas::resolve($attributes['schema'] ?? null);

        $editor = [
            'schema' => $schema,
            'title' => is_string($attributes['title'] ?? null) ? $attributes['title'] : '',
            'comment' => $attributes['comment'] ?? null,
            'color_labels' => $attributes['color_labels'] ?? RegionsMapRegions::defaultColorLabels(),
            'regions' => $attributes['regions'] ?? RegionsMapSchemas::defaultRegions($schema),
            '_schema_locked' => true,
        ];

        unset($attributes['schema'], $attributes['title'], $attributes['comment'], $attributes['color_labels'], $attributes['regions']);
        $attributes['regions_map_editor'] = $editor;

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function validateSchema(mixed $schema): array
    {
        if ($schema === null || $schema === '') {
            return [];
        }

        if (! is_string($schema)) {
            return ['regions_map.schema' => __('validation.regions_map.schema.string')];
        }

        if (! RegionsMapSchemas::isValid($schema)) {
            return ['regions_map.schema' => __('validation.regions_map.schema.invalid', [
                'schema' => $schema,
            ])];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validateComment(mixed $comment): array
    {
        if ($comment === null || $comment === '') {
            return [];
        }

        if (is_string($comment)) {
            if (mb_strlen($comment) > RegionsMapRegions::MAX_COMMENT_LENGTH) {
                return ['regions_map.comment' => __('validation.regions_map.comment.max', [
                    'max' => RegionsMapRegions::MAX_COMMENT_LENGTH,
                ])];
            }

            return [];
        }

        if (! is_array($comment)) {
            return ['regions_map.comment' => __('validation.regions_map.comment.invalid')];
        }

        if (count($comment) > RegionsMapRegions::MAX_COMMENT_LINES) {
            return ['regions_map.comment' => __('validation.regions_map.comment.too_many_lines', [
                'max' => RegionsMapRegions::MAX_COMMENT_LINES,
            ])];
        }

        foreach ($comment as $index => $line) {
            if (! is_string($line)) {
                return ['regions_map.comment' => __('validation.regions_map.comment.line_string', ['line' => $index + 1])];
            }
            if (mb_strlen($line) > RegionsMapRegions::MAX_COMMENT_LENGTH) {
                return ['regions_map.comment' => __('validation.regions_map.comment.line_max', [
                    'line' => $index + 1,
                    'max' => RegionsMapRegions::MAX_COMMENT_LENGTH,
                ])];
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validateColorLabels(mixed $colorLabels): array
    {
        if (! is_array($colorLabels)) {
            return ['regions_map.color_labels' => __('validation.regions_map.color_labels.required')];
        }

        $errors = [];
        $expected = RegionsMapColor::all();

        foreach ($expected as $color) {
            if (! array_key_exists($color, $colorLabels)) {
                $errors['regions_map.color_labels.'.$color] = __('validation.regions_map.color_labels.missing_key', [
                    'color' => $color,
                ]);
            }
        }

        foreach ($colorLabels as $key => $value) {
            if (! RegionsMapColor::isValid((string) $key)) {
                $errors['regions_map.color_labels.'.$key] = __('validation.regions_map.color_labels.invalid_key', [
                    'key' => $key,
                ]);

                continue;
            }
            if (! is_string($value)) {
                $errors['regions_map.color_labels.'.$key] = __('validation.regions_map.color_labels.string', [
                    'color' => $key,
                ]);

                continue;
            }
            if (mb_strlen($value) > RegionsMapRegions::MAX_COLOR_LABEL_LENGTH) {
                $errors['regions_map.color_labels.'.$key] = __('validation.regions_map.color_labels.max', [
                    'color' => $key,
                    'max' => RegionsMapRegions::MAX_COLOR_LABEL_LENGTH,
                ]);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRegions(mixed $regions, string $schema): array
    {
        if (! is_array($regions)) {
            return ['regions_map.regions' => __('validation.regions_map.regions.required')];
        }

        if (count($regions) !== RegionsMapSchemas::count($schema)) {
            return ['regions_map.regions' => __('validation.regions_map.regions.count', [
                'expected' => RegionsMapSchemas::count($schema),
                'actual' => count($regions),
            ])];
        }

        $seen = [];
        $errors = [];

        foreach ($regions as $index => $region) {
            $path = 'regions_map.regions.'.$index;

            if (! is_array($region)) {
                $errors[$path] = __('validation.regions_map.regions.item_array');

                continue;
            }

            $id = $region['id'] ?? null;
            if (! is_string($id) || $id === '') {
                $errors[$path.'.id'] = __('validation.regions_map.regions.id_required');

                continue;
            }

            if (! RegionsMapSchemas::isValidId($schema, $id)) {
                $errors[$path.'.id'] = __('validation.regions_map.regions.invalid_id', ['id' => $id]);

                continue;
            }

            if (isset($seen[$id])) {
                $errors[$path.'.id'] = __('validation.regions_map.regions.duplicate_id', ['id' => $id]);

                continue;
            }
            $seen[$id] = true;

            if (array_key_exists('color', $region) && $region['color'] !== null && $region['color'] !== '') {
                if (! is_string($region['color']) || ! RegionsMapColor::isValid($region['color'])) {
                    $errors[$path.'.color'] = __('validation.regions_map.regions.invalid_color', [
                        'id' => $id,
                        'color' => (string) ($region['color'] ?? ''),
                    ]);
                }
            }

            if (array_key_exists('comment', $region) && $region['comment'] !== null && $region['comment'] !== '') {
                if (! is_string($region['comment'])) {
                    $errors[$path.'.comment'] = __('validation.regions_map.regions.comment_string', ['id' => $id]);
                } elseif (mb_strlen($region['comment']) > RegionsMapRegions::MAX_REGION_COMMENT_LENGTH) {
                    $errors[$path.'.comment'] = __('validation.regions_map.regions.comment_max', [
                        'id' => $id,
                        'max' => RegionsMapRegions::MAX_REGION_COMMENT_LENGTH,
                    ]);
                }
            }
        }

        foreach (RegionsMapSchemas::ids($schema) as $expectedId) {
            if (! isset($seen[$expectedId])) {
                $errors['regions_map.regions.missing_id'] = __('validation.regions_map.regions.missing_id', [
                    'id' => $expectedId,
                ]);
                break;
            }
        }

        return $errors;
    }

    private function normalizeComment(mixed $comment): string|array|null
    {
        if ($comment === null || $comment === '') {
            return null;
        }

        if (is_string($comment)) {
            $trimmed = trim($comment);

            return $trimmed === '' ? null : $trimmed;
        }

        if (! is_array($comment)) {
            return null;
        }

        $lines = [];
        foreach ($comment as $line) {
            if (! is_string($line)) {
                continue;
            }
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $lines[] = $trimmed;
            }
        }

        if ($lines === []) {
            return null;
        }

        if (count($lines) === 1) {
            return $lines[0];
        }

        return $lines;
    }

    /** @param array<string, mixed> $colorLabels @return array<string, string> */
    private function normalizeColorLabels(array $colorLabels): array
    {
        $normalized = RegionsMapRegions::defaultColorLabels();

        foreach (RegionsMapColor::all() as $color) {
            if (array_key_exists($color, $colorLabels) && is_string($colorLabels[$color])) {
                $normalized[$color] = $colorLabels[$color];
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $regions
     * @return list<array<string, mixed>>
     */
    private function normalizeRegions(array $regions, string $schema): array
    {
        $byId = [];
        foreach ($regions as $region) {
            if (! is_array($region)) {
                continue;
            }
            $id = $region['id'] ?? null;
            if (! is_string($id) || ! RegionsMapSchemas::isValidId($schema, $id)) {
                continue;
            }
            $byId[$id] = $region;
        }

        $normalized = [];
        foreach (RegionsMapSchemas::ids($schema) as $id) {
            $region = $byId[$id] ?? ['id' => $id];
            $entry = ['id' => $id];

            $color = $region['color'] ?? RegionsMapColor::GRAY;
            if (is_string($color) && $color !== '' && $color !== RegionsMapColor::GRAY && RegionsMapColor::isValid($color)) {
                $entry['color'] = $color;
            }

            if (isset($region['comment']) && is_string($region['comment'])) {
                $comment = trim($region['comment']);
                if ($comment !== '') {
                    $entry['comment'] = $comment;
                }
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }
}
