<?php

namespace App\Nova\Fields;

use OneStrive\NovaImageCropper\ImageCropper;

class ImageCropperDnd extends ImageCropper
{
    /**
     * Custom component with drag-and-drop upload UI.
     *
     * @var string
     */
    public $component = 'nova-image-cropper-dnd';
}
