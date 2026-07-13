<?php

namespace App\Enums;

class RegionsMapColor
{
    public const GRAY = 'gray';
    public const YELLOW = 'yellow';
    public const RED = 'red';
    public const GREEN = 'green';
    public const BLACK = 'black';
    public const BLUE = 'blue';
    public const PURPLE = 'purple';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::GRAY,
            self::YELLOW,
            self::RED,
            self::GREEN,
            self::BLACK,
            self::BLUE,
            self::PURPLE,
        ];
    }

    public static function isValid(?string $color): bool
    {
        return $color !== null && in_array($color, self::all(), true);
    }

    /** @return array<string, string> slug => hex (editor UI only) */
    public static function hexMap(): array
    {
        return [
            self::GRAY => '#EBEBEB',
            self::YELLOW => '#F9A842',
            self::RED => '#E54839',
            self::GREEN => '#4CAF50',
            self::BLACK => '#000000',
            self::BLUE => '#2196F3',
            self::PURPLE => '#9C27B0',
        ];
    }
}
