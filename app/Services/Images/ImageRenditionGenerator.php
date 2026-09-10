<?php

namespace App\Services\Images;

use Exception;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickPixel;

final class ImageRenditionGenerator
{
    /**
     * @param  array{x?: int|float, y?: int|float, width?: int|float, height?: int|float}|null  $crop
     */
    public static function generateDisplay(
        string $absoluteOriginalPath,
        string $absoluteDisplayPath,
        ?array $crop = null,
        int $quality = ImageFormatPolicy::JPEG_QUALITY,
    ): bool {
        try {
            if (! is_readable($absoluteOriginalPath)) {
                Log::warning("ImageRenditionGenerator: original not readable: {$absoluteOriginalPath}");

                return false;
            }

            $dir = dirname($absoluteDisplayPath);
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                Log::error("ImageRenditionGenerator: cannot create directory: {$dir}");

                return false;
            }

            $imagick = new Imagick($absoluteOriginalPath);
            $imagick->setImageColorspace(Imagick::COLORSPACE_SRGB);

            if (method_exists($imagick, 'autoOrient')) {
                $imagick->autoOrient();
            } elseif (method_exists($imagick, 'autoOrientImage')) {
                $imagick->autoOrientImage();
            }

            if ($crop !== null) {
                $x = (int) round($crop['x'] ?? 0);
                $y = (int) round($crop['y'] ?? 0);
                $w = (int) round($crop['width'] ?? 0);
                $h = (int) round($crop['height'] ?? 0);

                if ($w > 0 && $h > 0) {
                    $imgW = $imagick->getImageWidth();
                    $imgH = $imagick->getImageHeight();
                    $x = max(0, min($x, $imgW - 1));
                    $y = max(0, min($y, $imgH - 1));
                    $w = min($w, $imgW - $x);
                    $h = min($h, $imgH - $y);
                    if ($w > 0 && $h > 0) {
                        $imagick->cropImage($w, $h, $x, $y);
                        $imagick->setImagePage(0, 0, 0, 0);
                    }
                }
            }

            // Flatten transparency onto white before JPEG encode.
            if ($imagick->getImageAlphaChannel()) {
                $flattened = new Imagick();
                $flattened->newImage(
                    $imagick->getImageWidth(),
                    $imagick->getImageHeight(),
                    new ImagickPixel('white')
                );
                $flattened->setImageFormat('jpeg');
                $flattened->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, 0);
                $imagick->clear();
                $imagick->destroy();
                $imagick = $flattened;
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality($quality);
            $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
            $imagick->writeImage($absoluteDisplayPath);
            $imagick->clear();
            $imagick->destroy();

            return true;
        } catch (Exception $e) {
            Log::error('ImageRenditionGenerator: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function dimensions(string $absolutePath): ?array
    {
        if (! is_readable($absolutePath)) {
            return null;
        }

        try {
            $imagick = new Imagick($absolutePath);
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $imagick->clear();
            $imagick->destroy();

            return ['width' => $width, 'height' => $height];
        } catch (Exception $e) {
            Log::error('ImageRenditionGenerator dimensions: '.$e->getMessage());

            return null;
        }
    }

    public static function supportsFormat(string $format): bool
    {
        $format = strtoupper(ltrim($format, '.'));

        try {
            $formats = Imagick::queryFormats($format);

            return is_array($formats) && $formats !== [];
        } catch (Exception) {
            return false;
        }
    }
}
