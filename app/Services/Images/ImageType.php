<?php

namespace App\Services\Images;

enum ImageType: string
{
    case PostCover = 'post_cover';
    case ContentImage = 'content_image';
    case OnlineImage = 'online_image';
    case UserPhoto = 'user_photo';
    case ThemeCover = 'theme';

    /**
     * Display is always required (cover crop applied to a pristine original).
     */
    public function alwaysNeedsDisplay(): bool
    {
        return $this === self::PostCover;
    }

    /**
     * Display only when original must be converted (webp/avif).
     */
    public function needsDisplayForConversionOnly(): bool
    {
        return ! $this->alwaysNeedsDisplay();
    }

    public static function tryFromPath(?string $path): ?self
    {
        if ($path === null || $path === '') {
            return null;
        }

        $segment = explode('/', ltrim($path, '/'), 2)[0] ?? '';

        return self::tryFrom($segment);
    }
}
