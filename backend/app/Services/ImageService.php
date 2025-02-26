<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageService
{
    const TYPE_POST_COVER = 'post_cover';
    const TYPE_USER_PHOTO = 'user_photo';
    const TYPE_CONTENT_IMAGE = 'content_image';

    const SIZE_SMALL = 'small';
    const SIZE_MEDIUM = 'medium';
    const SIZE_ORIGINAL = 'original';

    public static function getImageUrl($id, $type = self::TYPE_POST_COVER, $size = self::SIZE_ORIGINAL, $disk = 'public')
    {
        $hashes = self::generateShortHashes($id);
        $path = "{$type}/{$size}/{$hashes[0]}/{$hashes[1]}";

        return Storage::disk($disk)->url($path);
    }

    public static function generateShortHashes($id)
    {
        $md5Hash = md5($id);
        $hash1 = substr($md5Hash, 0, 4);
        $hash2 = substr($md5Hash, 4, 4);

        return [$hash1, $hash2];
    }
}