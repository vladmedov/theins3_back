<?php

namespace App\Rules;

use App\Services\Images\ImageRenditionGenerator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Dimension checks via Imagick (unlike Laravel's dimensions: rule, works with AVIF/WebP).
 */
class ImageDimensions implements ValidationRule
{
    public function __construct(
        private readonly ?int $minWidth = null,
        private readonly ?int $minHeight = null,
        private readonly ?int $maxWidth = null,
        private readonly ?int $maxHeight = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $dims = ImageRenditionGenerator::dimensions($value->getRealPath());
        if ($dims === null) {
            $fail(__('Unable to determine image dimensions.'));

            return;
        }

        $width = $dims['width'];
        $height = $dims['height'];

        if ($this->minWidth !== null && $width < $this->minWidth) {
            $fail(__('The image must be at least :min_width pixels wide.', ['min_width' => $this->minWidth]));
        }
        if ($this->minHeight !== null && $height < $this->minHeight) {
            $fail(__('The image must be at least :min_height pixels tall.', ['min_height' => $this->minHeight]));
        }
        if ($this->maxWidth !== null && $width > $this->maxWidth) {
            $fail(__('The image may not be greater than :max_width pixels wide.', ['max_width' => $this->maxWidth]));
        }
        if ($this->maxHeight !== null && $height > $this->maxHeight) {
            $fail(__('The image may not be greater than :max_height pixels tall.', ['max_height' => $this->maxHeight]));
        }
    }
}
