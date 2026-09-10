<?php

namespace App\Services\Images;

final class ImageFormatPolicy
{
    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    public const CONVERT_FROM = ['webp', 'avif'];

    public const DISPLAY_EXTENSION = 'jpg';

    public const JPEG_QUALITY = 100;

    public const MAX_KILOBYTES = 20480;

    /**
     * Laravel validation rule fragments for file uploads (no bare `image` rule — it rejects avif).
     *
     * @return list<string>
     */
    public static function validationRules(?int $maxKilobytes = null): array
    {
        $max = $maxKilobytes ?? self::MAX_KILOBYTES;

        return [
            'mimes:'.implode(',', self::ACCEPTED_EXTENSIONS),
            'max:'.$max,
        ];
    }

    /**
     * HTML accept attribute value.
     */
    public static function acceptAttribute(): string
    {
        return '.'.implode(',.', self::ACCEPTED_EXTENSIONS);
    }

    /**
     * @return list<string>
     */
    public static function acceptedExtensions(): array
    {
        return self::ACCEPTED_EXTENSIONS;
    }

    public static function needsConversion(?string $extensionOrPath): bool
    {
        $ext = self::normalizeExtension($extensionOrPath);

        return $ext !== null && in_array($ext, self::CONVERT_FROM, true);
    }

    public static function isAccepted(?string $extensionOrPath): bool
    {
        $ext = self::normalizeExtension($extensionOrPath);

        return $ext !== null && in_array($ext, self::ACCEPTED_EXTENSIONS, true);
    }

    public static function normalizeExtension(?string $extensionOrPath): ?string
    {
        if ($extensionOrPath === null || $extensionOrPath === '') {
            return null;
        }

        $value = strtolower(trim($extensionOrPath));
        $value = ltrim($value, '.');

        if (str_contains($value, '/') || str_contains($value, '.')) {
            $fromPath = pathinfo($value, PATHINFO_EXTENSION);
            if ($fromPath !== '') {
                $value = $fromPath;
            }
        }

        return $value !== '' ? $value : null;
    }
}
