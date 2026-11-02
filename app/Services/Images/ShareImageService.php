<?php

namespace App\Services\Images;

use App\Models\Post;
use App\Services\FrontendRevalidationService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickDraw;
use ImagickPixel;

class ShareImageService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const LINE_WIDTH = 15;
    private const IMAGE_OPACITY = 0.6;
    private const LOGO_TOP = 47;
    private const LOGO_LEFT = 235;
    private const JPEG_QUALITY = 88;

    public static function generate(Post $post): ?string
    {
        try {
            $disk = ImageStorageLayout::disk($post->language_code);
            $canvas = new Imagick();
            $canvas->newImage(self::WIDTH, self::HEIGHT, new ImagickPixel('#000000'));
            $canvas->setImageFormat('jpeg');

            self::drawBackgroundImage($canvas, $post);
            self::drawRedLine($canvas);
            self::drawLogo($canvas, $post->language_code);
            $canvas = self::flattenForJpeg($canvas);

            $path = self::getShareImagePath($post);
            $disk->makeDirectory(dirname($path));
            $fullPath = $disk->path($path);
            $canvas->setImageCompression(Imagick::COMPRESSION_JPEG);
            $canvas->setImageCompressionQuality(self::JPEG_QUALITY);
            $canvas->stripImage();
            $canvas->writeImage($fullPath);
            $canvas->destroy();

            self::cleanupStaleShareFiles($post, $disk, $path);

            app(FrontendRevalidationService::class)->queuePostChange((int) $post->id);
            app(FrontendRevalidationService::class)->flush();

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to generate share image for post #' . $post->id . ': ' . $e->getMessage());
            return null;
        }
    }

    public static function getShareImageUrl(Post $post): ?string
    {
        $disk = ImageStorageLayout::disk($post->language_code);
        $path = self::resolveExistingShareImagePath($post, $disk);
        if ($path === null) {
            return null;
        }

        $siteUrl = $post->language_code === 'ru'
            ? rtrim((string) config('app.ru_edition_host'), '/')
            : rtrim((string) config('app.en_edition_host'), '/');

        $url = $siteUrl . '/storage/' . $path;
        $version = self::shareVersion($disk, $path);

        return $version !== null ? $url . '?v=' . $version : $url;
    }

    /**
     * Stable share path per post (cover changes overwrite the same file).
     */
    public static function getShareImagePath(Post $post): string
    {
        $prefix = (string) intdiv((int) $post->id, 1000);

        return "share/{$prefix}/{$post->id}/{$post->id}.jpg";
    }

    private static function resolveExistingShareImagePath(Post $post, Filesystem $disk): ?string
    {
        $canonicalPath = self::getShareImagePath($post);
        if ($disk->exists($canonicalPath)) {
            return $canonicalPath;
        }

        $prefix = (string) intdiv((int) $post->id, 1000);
        $legacyFlatPath = "share/{$prefix}/{$post->id}.png";
        if ($disk->exists($legacyFlatPath)) {
            return $legacyFlatPath;
        }

        $postDirectory = "share/{$prefix}/{$post->id}";
        if (! $disk->exists($postDirectory)) {
            return null;
        }

        $candidates = [];
        foreach ($disk->files($postDirectory) as $path) {
            $lower = strtolower($path);
            if (str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg') || str_ends_with($lower, '.png')) {
                $candidates[] = $path;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (string $a, string $b) use ($disk): int {
            return $disk->lastModified($b) <=> $disk->lastModified($a);
        });

        return $candidates[0];
    }

    private static function shareVersion(Filesystem $disk, string $path): ?int
    {
        try {
            return $disk->lastModified($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function cleanupStaleShareFiles(Post $post, Filesystem $disk, string $keepPath): void
    {
        $prefix = (string) intdiv((int) $post->id, 1000);
        $legacyFlatPath = "share/{$prefix}/{$post->id}.png";
        if ($legacyFlatPath !== $keepPath && $disk->exists($legacyFlatPath)) {
            $disk->delete($legacyFlatPath);
        }

        $postDirectory = "share/{$prefix}/{$post->id}";
        if (! $disk->exists($postDirectory)) {
            return;
        }

        foreach ($disk->files($postDirectory) as $path) {
            if ($path !== $keepPath) {
                $disk->delete($path);
            }
        }
    }

    private static function flattenForJpeg(Imagick $canvas): Imagick
    {
        $canvas->setImageBackgroundColor(new ImagickPixel('#000000'));
        if (method_exists($canvas, 'mergeImageLayers')) {
            $flattened = $canvas->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            if ($flattened instanceof Imagick) {
                $canvas->clear();
                $canvas->destroy();

                $flattened->setImageColorspace(Imagick::COLORSPACE_SRGB);

                return $flattened;
            }
        } elseif ($canvas->getImageAlphaChannel()) {
            $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        }

        $canvas->setImageColorspace(Imagick::COLORSPACE_SRGB);

        return $canvas;
    }

    private static function drawBackgroundImage(Imagick $canvas, Post $post): void
    {
        if (empty($post->image)) {
            return;
        }

        $disk = ImageStorageLayout::disk($post->language_code);
        $servedRelative = ImageUrlResolver::servedRelativePath($post->image, $post->language_code);
        if ($servedRelative === null) {
            return;
        }
        $coverPath = $disk->path($servedRelative);
        if (!file_exists($coverPath)) {
            return;
        }

        try {
            $bg = new Imagick($coverPath);
            $bg->cropThumbnailImage(self::WIDTH - self::LINE_WIDTH, self::HEIGHT);

            $overlay = new Imagick();
            $overlay->newImage(
                self::WIDTH - self::LINE_WIDTH,
                self::HEIGHT,
                new ImagickPixel('rgba(0, 0, 0, ' . (1 - self::IMAGE_OPACITY) . ')')
            );
            $overlay->setImageFormat('png');
            $bg->compositeImage($overlay, Imagick::COMPOSITE_OVER, 0, 0);
            $overlay->destroy();

            $canvas->compositeImage($bg, Imagick::COMPOSITE_OVER, self::LINE_WIDTH, 0);
            $bg->destroy();
        } catch (\Exception $e) {
            Log::warning('Could not load background for share image: ' . $e->getMessage());
        }
    }

    private static function drawRedLine(Imagick $canvas): void
    {
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('#E64839'));
        $draw->rectangle(0, 0, self::LINE_WIDTH - 1, self::HEIGHT - 1);
        $canvas->drawImage($draw);
    }

    private static function drawLogo(Imagick $canvas, string $lang): void
    {
        $logoFile = $lang === 'en' ? 'share_logo_en.png' : 'share_logo.png';
        $logoPath = resource_path("share/{$logoFile}");

        if (!file_exists($logoPath)) {
            return;
        }

        try {
            $logo = new Imagick($logoPath);
            $canvas->compositeImage($logo, Imagick::COMPOSITE_OVER, self::LOGO_LEFT, self::LOGO_TOP);
            $logo->destroy();
        } catch (\Exception $e) {
            Log::warning('Could not load logo for share image: ' . $e->getMessage());
        }
    }
}
