<?php

namespace App\Services\Images;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for image uploads across Nova cover, gallery, avatar, theme.
 *
 * Future CKEditor / editor-block uploads should call the same methods.
 */
final class ImageIngestService
{
    /**
     * Store a gallery (or similar) upload: raw original + optional display rendition.
     *
     * @param  array{x?: int|float, y?: int|float, width?: int|float, height?: int|float}|null  $crop
     */
    public function ingest(
        UploadedFile $file,
        ImageType $type,
        string $languageCode,
        string|int $id,
        ?array $crop = null,
    ): StoredImage {
        $this->assertDecodable($file);

        $disk = ImageStorageLayout::disk($languageCode);
        $dir = ImageStorageLayout::directory($id, $type, ImageVariant::Original);
        $path = $file->store($dir, ImageStorageLayout::publicDiskForLanguage($languageCode));

        return $this->finalizeStored($id, $path, $type, $languageCode, $crop);
    }

    /**
     * After Nova (or other) has written the raw original to disk: relocate, build display, measure.
     *
     * @param  array{x?: int|float, y?: int|float, width?: int|float, height?: int|float}|null  $crop
     */
    public function finalizeStored(
        string|int $id,
        string $originalPath,
        ImageType $type,
        string $languageCode,
        ?array $crop = null,
    ): StoredImage {
        $path = ImageStorageLayout::relocateOriginalIfNeeded($id, $originalPath, $type, $languageCode);
        if ($path === null || $path === '') {
            throw new Exception('ImageIngestService: empty original path after relocate.');
        }

        $displayPath = $this->renditionFor($path, $type, $languageCode, $crop);

        $measurePath = $displayPath ?? $path;
        $absolute = ImageStorageLayout::absolutePath($measurePath, $languageCode);
        $dims = ImageRenditionGenerator::dimensions($absolute) ?? ['width' => 0, 'height' => 0];

        return new StoredImage(
            originalPath: $path,
            displayPath: $displayPath,
            width: (int) $dims['width'],
            height: (int) $dims['height'],
        );
    }

    /**
     * Create (or skip) display rendition for an already-stored original.
     *
     * @param  array{x?: int|float, y?: int|float, width?: int|float, height?: int|float}|null  $crop
     * @return string|null Relative display path, or null when not needed / failed
     */
    public function renditionFor(
        string $originalPath,
        ImageType $type,
        string $languageCode,
        ?array $crop = null,
    ): ?string {
        $needsDisplay = $type->alwaysNeedsDisplay()
            || ImageFormatPolicy::needsConversion($originalPath);

        if (! $needsDisplay) {
            return null;
        }

        $displayRelative = ImageStorageLayout::displayPathFor($originalPath);
        $absoluteOriginal = ImageStorageLayout::absolutePath($originalPath, $languageCode);
        $absoluteDisplay = ImageStorageLayout::absolutePath($displayRelative, $languageCode);

        $disk = ImageStorageLayout::disk($languageCode);
        $disk->makeDirectory(dirname($displayRelative));

        $ok = ImageRenditionGenerator::generateDisplay($absoluteOriginal, $absoluteDisplay, $crop);
        if (! $ok) {
            Log::warning("ImageIngestService: failed to generate display for {$originalPath}");

            return null;
        }

        return $displayRelative;
    }

    private function assertDecodable(UploadedFile $file): void
    {
        $ext = ImageFormatPolicy::normalizeExtension($file->getClientOriginalExtension() ?: $file->extension());
        if ($ext === null || ! ImageFormatPolicy::isAccepted($ext)) {
            throw ValidationException::withMessages([
                'file' => __('Unsupported image format.'),
            ]);
        }

        if (ImageFormatPolicy::needsConversion($ext) && ! ImageRenditionGenerator::supportsFormat($ext)) {
            throw ValidationException::withMessages([
                'file' => __('Server cannot decode :format images. Install ImageMagick with :format support.', [
                    'format' => strtoupper($ext),
                ]),
            ]);
        }
    }
}
