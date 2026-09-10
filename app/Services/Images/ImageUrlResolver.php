<?php

namespace App\Services\Images;

/**
 * Resolves public image URLs preferring display over original.
 */
final class ImageUrlResolver
{
    /** @var array<string, bool> */
    private static array $existenceCache = [];

    public static function clearCache(): void
    {
        self::$existenceCache = [];
    }

    /**
     * Path actually served (display if present, else original).
     */
    public static function servedRelativePath(?string $originalPath, ?string $languageCode): ?string
    {
        if ($originalPath === null || $originalPath === '') {
            return null;
        }

        $displayPath = ImageStorageLayout::displayPathFor($originalPath);
        if (self::pathExists($displayPath, $languageCode)) {
            return $displayPath;
        }

        return $originalPath;
    }

    public static function relative(?string $originalPath, ?string $languageCode): ?string
    {
        $path = self::servedRelativePath($originalPath, $languageCode);
        if ($path === null) {
            return null;
        }

        return '/storage/'.$path;
    }

    public static function absolute(?string $originalPath, ?string $languageCode, bool $canonicalHost = false): ?string
    {
        $path = self::servedRelativePath($originalPath, $languageCode);
        if ($path === null) {
            return null;
        }

        if ($canonicalHost) {
            $base = $languageCode === 'en'
                ? rtrim((string) config('app.en_canonical_host', config('app.en_edition_host')), '/')
                : rtrim((string) config('app.ru_canonical_host', config('app.ru_edition_host')), '/');

            return $base.'/storage/'.$path;
        }

        return ImageStorageLayout::disk($languageCode)->url($path);
    }

    /**
     * Backward-compatible helper used while migrating callers.
     * Builds URL from stored original path (with display fallback).
     */
    public static function forStored(
        string|int|null $id,
        ?string $originalPath,
        ImageType $type,
        ?string $languageCode = null,
        bool $includeDomain = false,
        bool $canonicalHost = false,
    ): ?string {
        if ($originalPath === null || $originalPath === '') {
            return null;
        }

        // Prefer path stored in DB when it already looks canonical.
        $path = $originalPath;
        if ($id !== null && $id !== '' && ! str_contains($originalPath, '/')) {
            $path = ImageStorageLayout::directory($id, $type, ImageVariant::Original).'/'.basename($originalPath);
        }

        if ($includeDomain) {
            return self::absolute($path, $languageCode, $canonicalHost);
        }

        return self::relative($path, $languageCode);
    }

    private static function pathExists(string $relativePath, ?string $languageCode): bool
    {
        $disk = ImageStorageLayout::publicDiskForLanguage($languageCode);
        $cacheKey = $disk.'|'.$relativePath;

        if (array_key_exists($cacheKey, self::$existenceCache)) {
            return self::$existenceCache[$cacheKey];
        }

        return self::$existenceCache[$cacheKey] = ImageStorageLayout::exists($relativePath, $languageCode);
    }
}
