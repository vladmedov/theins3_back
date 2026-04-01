<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;
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

    public static function generate(Post $post): ?string
    {
        try {
            $disk = Storage::disk(ImageService::publicDiskForLanguage($post->language_code));
            $canvas = new Imagick();
            $canvas->newImage(self::WIDTH, self::HEIGHT, new ImagickPixel('#000000'));
            $canvas->setImageFormat('png');

            self::drawBackgroundImage($canvas, $post);
            self::drawRedLine($canvas);
            self::drawLogo($canvas, $post->language_code);

            $path = self::getShareImagePath($post);
            $disk->makeDirectory(dirname($path));
            $fullPath = $disk->path($path);
            $canvas->writeImage($fullPath);
            $canvas->destroy();

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to generate share image for post #' . $post->id . ': ' . $e->getMessage());
            return null;
        }
    }

    public static function getShareImageUrl(Post $post): ?string
    {
        $disk = Storage::disk(ImageService::publicDiskForLanguage($post->language_code));
        $path = self::resolveExistingShareImagePath($post, $disk);
        if ($path === null) {
            return null;
        }

        $siteUrl = $post->language_code === 'ru'
            ? rtrim((string) config('app.ru_edition_host'), '/')
            : rtrim((string) config('app.en_edition_host'), '/');
        return $siteUrl . '/storage/' . $path;
    }

    public static function getShareImagePath(Post $post): string
    {
        $prefix = (string) intdiv((int) $post->id, 1000);
        $imageToken = self::shareNameTokenFromImage($post);
        return "share/{$prefix}/{$post->id}/{$imageToken}.png";
    }

    private static function shareNameTokenFromImage(Post $post): string
    {
        $filename = pathinfo((string) $post->image, PATHINFO_FILENAME);
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $filename) ?? '';
        $filename = trim($filename, '-_');

        return $filename !== '' ? $filename : 'image';
    }

    private static function resolveExistingShareImagePath(Post $post, $disk): ?string
    {
        $currentPath = self::getShareImagePath($post);
        if ($disk->exists($currentPath)) {
            return $currentPath;
        }

        $prefix = (string) intdiv((int) $post->id, 1000);
        $legacyFlatPath = "share/{$prefix}/{$post->id}.png";
        if ($disk->exists($legacyFlatPath)) {
            return $legacyFlatPath;
        }

        $postDirectory = "share/{$prefix}/{$post->id}";
        foreach ($disk->files($postDirectory) as $path) {
            if (str_ends_with(strtolower($path), '.png')) {
                return $path;
            }
        }

        return null;
    }

    private static function drawBackgroundImage(Imagick $canvas, Post $post): void
    {
        if (empty($post->image)) {
            return;
        }

        $disk = Storage::disk(ImageService::publicDiskForLanguage($post->language_code));
        $coverPath = $disk->path($post->image);
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
