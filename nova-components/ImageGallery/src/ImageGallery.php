<?php

namespace Medov\ImageGallery;

use App\Services\ImageService;
use Laravel\Nova\Fields\Field;
use Illuminate\Support\Facades\Storage;

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
        return $this->storageDisk(ImageService::publicDiskForLanguage($languageCode));
    }

    public function jsonSerialize(): array
    {
        $disk = $this->storageDisk ?? ImageService::publicDiskForLanguage(app()->getLocale());

        return array_merge(parent::jsonSerialize(), [
            'storageUrl' => Storage::disk($disk)->url(''),
            'storageDisk' => $disk,
        ]);
    }
}
