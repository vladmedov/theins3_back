<?php

namespace App\Support\RegionsMap;

use App\Enums\RegionsMapColor;

class RegionsMapRegions
{
    /** @var array<string, string> ISO code => Russian label (editor UI) */
    private const LABELS = [
        'RU-AD' => 'Адыгея',
        'RU-AL' => 'Алтай',
        'RU-ALT' => 'Алтайский край',
        'RU-AMU' => 'Амурская область',
        'RU-ARK' => 'Архангельская область',
        'RU-AST' => 'Астраханская область',
        'RU-BA' => 'Башкортостан',
        'RU-BEL' => 'Белгородская область',
        'RU-BRY' => 'Брянская область',
        'RU-BU' => 'Бурятия',
        'RU-CE' => 'Чечня',
        'RU-CHE' => 'Челябинская область',
        'RU-CHU' => 'Чукотский АО',
        'RU-CU' => 'Чувашия',
        'RU-DA' => 'Дагестан',
        'RU-IN' => 'Ингушетия',
        'RU-IRK' => 'Иркутская область',
        'RU-IVA' => 'Ивановская область',
        'RU-KAM' => 'Камчатский край',
        'RU-KB' => 'Кабардино-Балкария',
        'RU-KC' => 'Карачаево-Черкесия',
        'RU-KDA' => 'Краснодарский край',
        'RU-KEM' => 'Кемеровская область',
        'RU-KGD' => 'Калининградская область',
        'RU-KGN' => 'Курганская область',
        'RU-KHA' => 'Хабаровский край',
        'RU-KHM' => 'ХМАО',
        'RU-KIR' => 'Кировская область',
        'RU-KK' => 'Хакасия',
        'RU-KL' => 'Калмыкия',
        'RU-KLU' => 'Калужская область',
        'RU-KO' => 'Коми',
        'RU-KOS' => 'Костромская область',
        'RU-KR' => 'Карелия',
        'RU-KRS' => 'Курская область',
        'RU-KYA' => 'Красноярский край',
        'RU-LEN' => 'Ленинградская область',
        'RU-LIP' => 'Липецкая область',
        'RU-MAG' => 'Магаданская область',
        'RU-ME' => 'Марий Эл',
        'RU-MO' => 'Мордовия',
        'RU-MOS' => 'Московская область',
        'RU-MOW' => 'Москва',
        'RU-MUR' => 'Мурманская область',
        'RU-NEN' => 'Ненецкий АО',
        'RU-NGR' => 'Новгородская область',
        'RU-NIZ' => 'Нижегородская область',
        'RU-NVS' => 'Новосибирская область',
        'RU-OMS' => 'Омская область',
        'RU-ORE' => 'Оренбургская область',
        'RU-ORL' => 'Орловская область',
        'RU-PER' => 'Пермский край',
        'RU-PNZ' => 'Пензенская область',
        'RU-PRI' => 'Приморский край',
        'RU-PSK' => 'Псковская область',
        'RU-ROS' => 'Ростовская область',
        'RU-RYA' => 'Рязанская область',
        'RU-SA' => 'Якутия',
        'RU-SAK' => 'Сахалинская область',
        'RU-SAM' => 'Самарская область',
        'RU-SAR' => 'Саратовская область',
        'RU-SE' => 'Северная Осетия',
        'RU-SMO' => 'Смоленская область',
        'RU-SPE' => 'Санкт-Петербург',
        'RU-STA' => 'Ставропольский край',
        'RU-SVE' => 'Свердловская область',
        'RU-TA' => 'Татарстан',
        'RU-TAM' => 'Тамбовская область',
        'RU-TOM' => 'Томская область',
        'RU-TUL' => 'Тульская область',
        'RU-TVE' => 'Тверская область',
        'RU-TY' => 'Тыва',
        'RU-TYU' => 'Тюменская область',
        'RU-UD' => 'Удмуртия',
        'RU-ULY' => 'Ульяновская область',
        'RU-VGG' => 'Волгоградская область',
        'RU-VLA' => 'Владимирская область',
        'RU-VLG' => 'Вологодская область',
        'RU-VOR' => 'Воронежская область',
        'RU-YAN' => 'ЯНАО',
        'RU-YAR' => 'Ярославская область',
        'RU-YEV' => 'Еврейская АО',
        'RU-ZAB' => 'Забайкальский край',
        'UA-43' => 'Крым',
        'UA-14' => 'Донецкая область',
        'UA-09' => 'Луганская область',
        'UA-23' => 'Запорожская область',
        'UA-65' => 'Херсонская область',
        'UA-40' => 'Севастополь',
        'AT' => 'Австрия',
        'BE' => 'Бельгия',
        'BG' => 'Болгария',
        'HR' => 'Хорватия',
        'CY' => 'Кипр',
        'CZ' => 'Чехия',
        'DE' => 'Германия',
        'DK' => 'Дания',
        'EE' => 'Эстония',
        'ES' => 'Испания',
        'FI' => 'Финляндия',
        'FR' => 'Франция',
        'GR' => 'Греция',
        'HU' => 'Венгрия',
        'IE' => 'Ирландия',
        'IT' => 'Италия',
        'LT' => 'Литва',
        'LU' => 'Люксембург',
        'LV' => 'Латвия',
        'MT' => 'Мальта',
        'NL' => 'Нидерланды',
        'PL' => 'Польша',
        'PT' => 'Португалия',
        'RO' => 'Румыния',
        'SE' => 'Швеция',
        'SI' => 'Словения',
        'SK' => 'Словакия',
    ];

    public const MAX_TITLE_LENGTH = 500;

    public const MAX_COMMENT_LENGTH = 500;

    public const MAX_COMMENT_LINES = 10;

    public const MAX_COLOR_LABEL_LENGTH = 500;

    public const MAX_REGION_COMMENT_LENGTH = 500;

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public static function sortIds(array $ids): array
    {
        usort($ids, fn (string $a, string $b): int => self::compareRegionLabels(
            self::LABELS[$a] ?? $a,
            self::LABELS[$b] ?? $b,
        ));

        return $ids;
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return RegionsMapSchemas::ids(RegionsMapSchemas::formDefault());
    }

    public static function label(string $id): ?string
    {
        return self::LABELS[$id] ?? null;
    }

    public static function hasId(string $id): bool
    {
        return array_key_exists($id, self::LABELS);
    }

    private static function compareRegionLabels(string $left, string $right): int
    {
        if (extension_loaded('intl')) {
            static $collator = null;
            $collator ??= new \Collator('ru_RU');

            return $collator->compare($left, $right);
        }

        return strcmp(mb_strtolower($left, 'UTF-8'), mb_strtolower($right, 'UTF-8'));
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function count(): int
    {
        return RegionsMapSchemas::count(RegionsMapSchemas::formDefault());
    }

    public static function isValidId(string $id): bool
    {
        return self::hasId($id);
    }

    /** @return array<string, string> */
    public static function defaultColorLabels(): array
    {
        $labels = [];
        foreach (RegionsMapColor::all() as $color) {
            $labels[$color] = '';
        }

        return $labels;
    }

    /** @return list<array{id: string}> */
    public static function defaultRegions(): array
    {
        return RegionsMapSchemas::defaultRegions(RegionsMapSchemas::formDefault());
    }

    /** @return array{schema: string, title: string, comment: null, color_labels: array<string, string>, regions: list<array{id: string}>} */
    public static function defaultEditorState(): array
    {
        return [
            'schema' => RegionsMapSchemas::formDefault(),
            'title' => '',
            'comment' => null,
            'color_labels' => self::defaultColorLabels(),
            'regions' => self::defaultRegions(),
        ];
    }
}
