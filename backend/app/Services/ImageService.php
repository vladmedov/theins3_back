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

    public const SIZE_SMALL = 'small';
    public const SIZE_MEDIUM = 'medium';
    public const SIZE_ORIGINAL = 'original';
    public const SIZE_CROPPED = 'cropped';
    private const WIDTH_SMALL = 768;
    private const WIDTH_MEDIUM = 1536;

    public static function getImagePath($id, string $type = self::TYPE_POST_COVER, string $size = self::SIZE_ORIGINAL): string
    {
        $hashes = self::generateShortHashes((string)$id);
        return "{$type}/{$size}/{$hashes[0]}/{$hashes[1]}";
    }

    public static function generateShortHashes(string $id): array
    {
        $md5Hash = md5($id);
        $hash1 = substr($md5Hash, 0, 4);
        $hash2 = substr($md5Hash, 4, 4);

        return [$hash1, $hash2];
    }

    public static function createImageVariants($id, ?string $imagePath, string $type = self::TYPE_POST_COVER): bool
    {
        if (empty($imagePath)) {
            return false;
        }
        
        try {
            $originalPath = Storage::disk('public')->path($imagePath);
            
            if (!file_exists($originalPath)) {
                Log::warning("Файл не существует: {$originalPath}");
                return false;
            }
            
            $filename = basename($imagePath);
            $originalWidth = self::getImageWidth($originalPath);
            
            if ($originalWidth === null) {
                return false;
            }
            
            $smallSuccess = $originalWidth > self::WIDTH_SMALL 
                ? self::createResizedImage($originalPath, $id, $filename, $type, self::SIZE_SMALL, self::WIDTH_SMALL)
                : self::createImageCopy($originalPath, $id, $filename, $type, self::SIZE_SMALL);
            
            $mediumSuccess = $originalWidth > self::WIDTH_MEDIUM 
                ? self::createResizedImage($originalPath, $id, $filename, $type, self::SIZE_MEDIUM, self::WIDTH_MEDIUM)
                : self::createImageCopy($originalPath, $id, $filename, $type, self::SIZE_MEDIUM);
            
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
    
    private static function createResizedImage(string $originalPath, $id, string $filename, string $type, string $sizeType, int $width): bool
    {
        try {
            $dir = self::getImagePath($id, $type, $sizeType);
            Storage::disk('public')->makeDirectory($dir);
            $path = Storage::disk('public')->path($dir . '/' . $filename);
            
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

    private static function createImageCopy(string $originalPath, $id, string $filename, string $type, string $sizeType): bool
    {
        try {
            $dir = self::getImagePath($id, $type, $sizeType);
            Storage::disk('public')->makeDirectory($dir);
            $path = Storage::disk('public')->path($dir . '/' . $filename);
            
            if (!copy($originalPath, $path)) {
                throw new Exception("Не удалось скопировать файл из {$originalPath} в {$path}");
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Ошибка при копировании изображения: ' . $e->getMessage());
            return false;
        }
    }

    public static function getImageUrl($id, ?string $imagePath, string $type = self::TYPE_POST_COVER, string $size = self::SIZE_ORIGINAL): ?string
    {
        if (empty($imagePath)) {
            return null;
        }
        
        $filename = basename($imagePath);
        $path = self::getImagePath($id, $type, $size) . '/' . $filename;
            
        return Storage::disk('public')->url($path);
    }
}