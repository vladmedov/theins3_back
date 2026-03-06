<?php

namespace Medov\ImageGallery;

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

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'storageUrl' => Storage::disk('public')->url(''),
        ]);
    }
}
