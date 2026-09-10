<?php

namespace Medov\ImageGallery;

use App\Services\Images\ImageFormatPolicy;
use App\Services\Images\ImageStorageLayout;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Field;

class ImageGallery extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'image-gallery';

    protected ?string $storageDisk = null;

    public function storageDisk(string $disk): static
    {
        $this->storageDisk = $disk;

        return $this;
    }

    public function storageLanguage(string $languageCode): static
    {
        return $this->storageDisk(ImageStorageLayout::publicDiskForLanguage($languageCode));
    }

    public function jsonSerialize(): array
    {
        $disk = $this->storageDisk ?? ImageStorageLayout::publicDiskForLanguage(app()->getLocale());

        return array_merge(parent::jsonSerialize(), [
            'storageUrl' => Storage::disk($disk)->url(''),
            'storageDisk' => $disk,
            'acceptedTypes' => $this->meta['acceptedTypes'] ?? ImageFormatPolicy::acceptAttribute(),
            'acceptedExtensions' => $this->meta['acceptedExtensions'] ?? ImageFormatPolicy::acceptedExtensions(),
        ]);
    }
}
