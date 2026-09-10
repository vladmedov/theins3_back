<?php

namespace App\Services\Images;

final class StoredImage
{
    public function __construct(
        public readonly string $originalPath,
        public readonly ?string $displayPath,
        public readonly int $width,
        public readonly int $height,
        public readonly ?string $mime = null,
    ) {
    }

    /**
     * Relative path preferred for public delivery (display when present).
     */
    public function servedPath(): string
    {
        return $this->displayPath ?? $this->originalPath;
    }
}
