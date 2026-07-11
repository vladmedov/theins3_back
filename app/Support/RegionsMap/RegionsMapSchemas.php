<?php

namespace App\Support\RegionsMap;

class RegionsMapSchemas
{
    public const RU_AND_OCCUPIED_UA = '2026_06_26_ru_and_occupied_ua';

    public const EU_MEMBER_STATES = '2026_06_26_eu_member_states';

    /** @var array<string, list<string>> */
    private const DEFINITIONS = [
        self::RU_AND_OCCUPIED_UA => [
            'RU-AD', 'RU-AL', 'RU-ALT', 'RU-AMU', 'RU-ARK', 'RU-AST', 'RU-BA', 'RU-BEL', 'RU-BRY',
            'RU-BU', 'RU-CE', 'RU-CHE', 'RU-CHU', 'RU-CU', 'RU-DA', 'RU-IN', 'RU-IRK', 'RU-IVA',
            'RU-KAM', 'RU-KB', 'RU-KC', 'RU-KDA', 'RU-KEM', 'RU-KGD', 'RU-KGN', 'RU-KHA', 'RU-KHM',
            'RU-KIR', 'RU-KK', 'RU-KL', 'RU-KLU', 'RU-KO', 'RU-KOS', 'RU-KR', 'RU-KRS', 'RU-KYA',
            'RU-LEN', 'RU-LIP', 'RU-MAG', 'RU-ME', 'RU-MO', 'RU-MOS', 'RU-MOW', 'RU-MUR', 'RU-NEN',
            'RU-NGR', 'RU-NIZ', 'RU-NVS', 'RU-OMS', 'RU-ORE', 'RU-ORL', 'RU-PER', 'RU-PNZ', 'RU-PRI',
            'RU-PSK', 'RU-ROS', 'RU-RYA', 'RU-SA', 'RU-SAK', 'RU-SAM', 'RU-SAR', 'RU-SE', 'RU-SMO',
            'RU-SPE', 'RU-STA', 'RU-SVE', 'RU-TA', 'RU-TAM', 'RU-TOM', 'RU-TUL', 'RU-TVE', 'RU-TY',
            'RU-TYU', 'RU-UD', 'RU-ULY', 'RU-VGG', 'RU-VLA', 'RU-VLG', 'RU-VOR', 'RU-YAN', 'RU-YAR',
            'RU-YEV', 'RU-ZAB',
            'UA-43', 'UA-14', 'UA-09', 'UA-23', 'UA-65', 'UA-40',
        ],
        self::EU_MEMBER_STATES => [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES',
            'FI', 'FR', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
            'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
        ],
    ];

    /** Схема по умолчанию только для нового блока в форме Nova. В сохранённых данных всегда явный slug. */
    public static function formDefault(): string
    {
        return self::RU_AND_OCCUPIED_UA;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function isValid(string $schema): bool
    {
        return array_key_exists($schema, self::DEFINITIONS);
    }

    /** @return list<string> */
    public static function ids(string $schema): array
    {
        if (! self::isValid($schema)) {
            return [];
        }

        return RegionsMapRegions::sortIds(self::DEFINITIONS[$schema]);
    }

    /** @return array<string, string> */
    public static function labels(string $schema): array
    {
        $labels = [];
        foreach (self::ids($schema) as $id) {
            $label = RegionsMapRegions::label($id);
            if ($label !== null) {
                $labels[$id] = $label;
            }
        }

        return $labels;
    }

    public static function count(string $schema): int
    {
        return count(self::ids($schema));
    }

    public static function isValidId(string $schema, string $id): bool
    {
        return in_array($id, self::ids($schema), true);
    }

    /** @return list<array{id: string}> */
    public static function defaultRegions(string $schema): array
    {
        $regions = [];
        foreach (self::ids($schema) as $id) {
            $regions[] = ['id' => $id];
        }

        return $regions;
    }

    /** @return array<string, string> schema slug => editor label */
    public static function editorLabels(): array
    {
        return [
            self::RU_AND_OCCUPIED_UA => 'РФ и оккупированные территории Украины (26.06.2026)',
            self::EU_MEMBER_STATES => 'Страны — члены ЕС (26.06.2026)',
        ];
    }

    public static function editorLabel(string $schema): string
    {
        return self::editorLabels()[$schema] ?? $schema;
    }

    /** Подставляет slug для старых блоков без schema; валидные значения возвращает как есть. */
    public static function resolve(?string $schema): string
    {
        if (is_string($schema) && $schema !== '' && self::isValid($schema)) {
            return $schema;
        }

        return self::RU_AND_OCCUPIED_UA;
    }
}
