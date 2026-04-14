<?php

namespace App\Services;

/**
 * Tooltip/termin spans use {@code data-description} with value = standard base64 of the UTF-8 bytes
 * of the description HTML string. Same encoding everywhere: public API expansion, content migration,
 * CSV-backed glossary, and Nova Hint editor (JS must decode with UTF-8).
 */
final class TerminDescriptionAttributeCodec
{
    public static function encode(?string $html): string
    {
        return base64_encode($html ?? '');
    }

    public static function decode(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $decoded = base64_decode($encoded, true);

        return $decoded === false ? '' : $decoded;
    }
}
