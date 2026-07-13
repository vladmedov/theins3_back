<?php

namespace Medov\RegionsMapEditor;

use App\Enums\RegionsMapColor;
use App\Support\RegionsMap\RegionsMapRegions;
use App\Support\RegionsMap\RegionsMapSchemas;
use Laravel\Nova\Fields\Field;

class RegionsMapEditor extends Field
{
    public $component = 'regions-map-editor';

    public function jsonSerialize(): array
    {
        $schema = $this->resolveSchemaFromValue($this->value);

        return array_merge(parent::jsonSerialize(), [
            'regionLabels' => RegionsMapSchemas::labels($schema),
            'regionIds' => RegionsMapSchemas::ids($schema),
            'schemaRegions' => collect(RegionsMapSchemas::all())
                ->mapWithKeys(fn (string $key): array => [
                    $key => [
                        'ids' => RegionsMapSchemas::ids($key),
                        'labels' => RegionsMapSchemas::labels($key),
                    ],
                ])
                ->all(),
            'colors' => RegionsMapColor::all(),
            'colorHexMap' => RegionsMapColor::hexMap(),
            'defaults' => RegionsMapRegions::defaultEditorState(),
            'defaultSchema' => RegionsMapSchemas::formDefault(),
            'schemas' => collect(RegionsMapSchemas::all())
                ->map(fn (string $key): array => [
                    'value' => $key,
                    'label' => RegionsMapSchemas::editorLabel($key),
                ])
                ->values()
                ->all(),
            'limits' => [
                'title' => RegionsMapRegions::MAX_TITLE_LENGTH,
                'comment' => RegionsMapRegions::MAX_COMMENT_LENGTH,
                'commentLines' => RegionsMapRegions::MAX_COMMENT_LINES,
                'colorLabel' => RegionsMapRegions::MAX_COLOR_LABEL_LENGTH,
                'regionComment' => RegionsMapRegions::MAX_REGION_COMMENT_LENGTH,
            ],
            'uiLanguage' => app()->getLocale() === 'ru' ? 'ru' : 'en',
            'schemaLocked' => $this->isSchemaLocked($this->value),
        ]);
    }

    private function isSchemaLocked(mixed $value): bool
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return false;
        }

        return ($value['_schema_locked'] ?? false) === true;
    }

    private function resolveSchemaFromValue(mixed $value): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return RegionsMapSchemas::formDefault();
        }

        return RegionsMapSchemas::resolve($value['schema'] ?? null);
    }
}
