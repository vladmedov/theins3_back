<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;
use Imagick;

class ImageService
{
    public const TYPE_POST_COVER = 'post_cover';
    public const TYPE_USER_PHOTO = 'user_photo';
    public const TYPE_CONTENT_IMAGE = 'content_image';
    public const TYPE_THEME_COVER = 'theme';
    public const TYPE_ONLINE_IMAGE = 'online_image';

    public const SIZE_SMALL = 'small';
    public const SIZE_MEDIUM = 'medium';
    public const SIZE_ORIGINAL = 'original';
    public const SIZE_CROPPED = 'cropped';
    private const WIDTH_SMALL = 768;
    private const WIDTH_MEDIUM = 1536;
    /** Минимальная ширина оригинала, при которой создаём уменьшенный small; иначе копируем без ресайза (чтобы не сжимать картинки 768–1024px) */
    private const MIN_WIDTH_TO_RESIZE_SMALL = 1024;

    public static function publicDiskForLanguage(?string $languageCode): string
    {
        return $languageCode === 'en' ? 'en_public' : 'ru_public';
    }

    public static function publicUrlForPath(string $path, ?string $languageCode): string
    {
        return Storage::disk(self::publicDiskForLanguage($languageCode))->url($path);
    }

    public static function getImagePath($id, string $type = self::TYPE_POST_COVER, string $size = self::SIZE_ORIGINAL): string
    {
        $idStr = (string) $id;

        if (is_numeric($id)) {
            $prefix = (string) intdiv((int) $id, 1000);
        } else {
            $prefix = substr($idStr, 0, 3);
        }

        return "{$type}/{$size}/{$prefix}/{$idStr}";
    }

    public static function createImageVariants(
        $id,
        ?string $imagePath,
        string $type = self::TYPE_POST_COVER,
        string $languageCode
    ): bool
    {
        if (empty($imagePath)) {
            return false;
        }
        
        try {
            $disk = Storage::disk(self::publicDiskForLanguage($languageCode));
            $originalPath = $disk->path($imagePath);
            
            if (!file_exists($originalPath)) {
                Log::warning("Файл не существует: {$originalPath}");
                return false;
            }
            
            $filename = basename($imagePath);
            $originalWidth = self::getImageWidth($originalPath);
            
            if ($originalWidth === null) {
                return false;
            }
            
            $smallSuccess = $originalWidth >= self::MIN_WIDTH_TO_RESIZE_SMALL
                ? self::createResizedImage($originalPath, $id, $filename, $type, self::SIZE_SMALL, self::WIDTH_SMALL, $languageCode)
                : self::createImageCopy($originalPath, $id, $filename, $type, self::SIZE_SMALL, $languageCode);
            
            $mediumSuccess = $originalWidth > self::WIDTH_MEDIUM 
                ? self::createResizedImage($originalPath, $id, $filename, $type, self::SIZE_MEDIUM, self::WIDTH_MEDIUM, $languageCode)
                : self::createImageCopy($originalPath, $id, $filename, $type, self::SIZE_MEDIUM, $languageCode);
            
            return $smallSuccess && $mediumSuccess;
        } catch (Exception $e) {
            Log::error('Ошибка при создании вариантов изображения: ' . $e->getMessage());
            return false;
        }
    }
    
    private static function getImageWidth(string $imagePath): ?int
    {
        try {
            $imagick = new Imagick($imagePath);
            $width = $imagick->getImageWidth();
            $imagick->clear();
            $imagick->destroy();
            return $width;
        } catch (Exception $e) {
            Log::error('Ошибка при определении размеров изображения: ' . $e->getMessage());
            return null;
        }
    }
    
    private static function createResizedImage(
        string $originalPath,
        $id,
        string $filename,
        string $type,
        string $sizeType,
        int $width,
        string $languageCode
    ): bool
    {
        try {
            $dir = self::getImagePath($id, $type, $sizeType);
            $disk = Storage::disk(self::publicDiskForLanguage($languageCode));
            $disk->makeDirectory($dir);
            $path = $disk->path($dir . '/' . $filename);
            
            $imagick = new Imagick($originalPath);
            $imagick->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $imagick->setImageDepth(16);
            $imagick->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);
            $imagick->setImageCompressionQuality(100);
            $imagick->writeImage($path);
            $imagick->clear();
            $imagick->destroy();
            
            return true;
        } catch (Exception $e) {
            Log::error('Ошибка при создании измененной версии изображения: ' . $e->getMessage());
            return false;
        }
    }

    private static function createImageCopy(
        string $originalPath,
        $id,
        string $filename,
        string $type,
        string $sizeType,
        string $languageCode
    ): bool
    {
        try {
            $dir = self::getImagePath($id, $type, $sizeType);
            $disk = Storage::disk(self::publicDiskForLanguage($languageCode));
            $disk->makeDirectory($dir);
            $path = $disk->path($dir . '/' . $filename);
            
            if (!copy($originalPath, $path)) {
                throw new Exception("Не удалось скопировать файл из {$originalPath} в {$path}");
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Ошибка при копировании изображения: ' . $e->getMessage());
            return false;
        }
    }

    public static function getImageUrl(
        $id,
        ?string $imagePath,
        string $type = self::TYPE_POST_COVER,
        string $size = self::SIZE_ORIGINAL,
        bool $includeDomain = true,
        ?string $languageCode = null
    ): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        $filename = basename($imagePath);
        $path = self::getImagePath($id, $type, $size) . '/' . $filename;

        if (!$includeDomain) {
            return '/storage/' . $path;
        }

        return self::publicUrlForPath($path, $languageCode);
    }
}