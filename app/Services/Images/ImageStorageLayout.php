<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Storage;

final class ImageStorageLayout
{
    public static function publicDiskForLanguage(?string $languageCode): string
    {
        return $languageCode === 'en' ? 'en_public' : 'ru_public';
    }

    public static function disk(?string $languageCode)
    {
        return Storage::disk(self::publicDiskForLanguage($languageCode));
    }

    /**
     * Directory for an image id under type/variant (without trailing slash / filename).
     * $id may be null on Nova create forms before the model is persisted; relocate later.
     */
    public static function directory(string|int|null $id, ImageType $type, ImageVariant $variant = ImageVariant::Original): string
    {
        $idStr = (string) ($id ?? '');

        if ($id !== null && $id !== '' && is_numeric($id)) {
            $prefix = (string) intdiv((int) $id, 1000);
        } else {
            $prefix = substr($idStr !== '' ? $idStr : 'tmp', 0, 3);
        }

        $idSegment = $idStr !== '' ? $idStr : 'tmp';

        return "{$type->value}/{$variant->value}/{$prefix}/{$idSegment}";
    }

    /**
     * Derive display relative path from an original relative path.
     * Example: post_cover/original/12/12345/photo.webp → post_cover/display/12/12345/photo.jpg
     */
    public static function displayPathFor(string $originalPath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $originalPath), '/');
        $parts = explode('/', $normalized);

        if (count($parts) >= 2 && $parts[1] === ImageVariant::Original->value) {
            $parts[1] = ImageVariant::Display->value;
        } elseif (count($parts) >= 2 && $parts[1] === ImageVariant::Display->value) {
            // already display dir
        } else {
            // Fallback: insert display after type if possible
            if (count($parts) >= 1) {
                array_splice($parts, 1, 0, [ImageVariant::Display->value]);
            }
        }

        $filename = array_pop($parts);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $parts[] = $base.'.'.ImageFormatPolicy::DISPLAY_EXTENSION;

        return implode('/', $parts);
    }

    /**
     * Nova may upload before the numeric id exists; move into the canonical original directory.
     *
     * @return string|null Canonical original path, or null if $currentPath was empty
     */
    public static function relocateOriginalIfNeeded(
        string|int|null $entityId,
        ?string $currentPath,
        ImageType $type,
        string $languageCode,
    ): ?string {
        if ($currentPath === null || $currentPath === '') {
            return null;
        }
        if ($entityId === null || $entityId === '') {
            return $currentPath;
        }

        $correctDir = self::directory($entityId, $type, ImageVariant::Original);
        $filename = basename($currentPath);
        $correctPath = $correctDir.'/'.$filename;

        $disk = self::disk($languageCode);
        if ($currentPath !== $correctPath && $disk->exists($currentPath)) {
            $disk->makeDirectory($correctDir);
            $disk->move($currentPath, $correctPath);

            return $correctPath;
        }

        return $currentPath;
    }

    public static function absolutePath(string $relativePath, ?string $languageCode): string
    {
        return self::disk($languageCode)->path($relativePath);
    }

    public static function exists(string $relativePath, ?string $languageCode): bool
    {
        return self::disk($languageCode)->exists($relativePath);
    }
}
