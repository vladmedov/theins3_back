<?php

namespace App\Nova\Fields;

use App\Services\Images\ImageStorageLayout;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;
use OneStrive\NovaImageCropper\ImageCropper;
use ReflectionMethod;

/**
 * Stores the uploaded file as a raw original (no Intervention crop).
 * Crop coordinates from `{attribute}_data` are attached to the model for display rendition generation.
 */
class ImageCropperDnd extends ImageCropper
{
    /**
     * Custom component with drag-and-drop upload UI.
     *
     * @var string
     */
    public $component = 'nova-image-cropper-dnd';

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  object  $model
     */
    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute): mixed
    {
        if (empty($request->{$requestAttribute})) {
            return false;
        }

        $previousFileName = $model->{$attribute};

        $cropPayload = $request->{$this->attribute.'_data'} ?? null;
        if (is_string($cropPayload) && $cropPayload !== '') {
            $decoded = json_decode($cropPayload, true);
            if (is_array($decoded)) {
                $model->pendingImageCropData = $decoded;
            }
        }

        // Skip ImageCropper::fillAttribute (which crops via Intervention).
        // Store the raw upload through Nova's File field implementation.
        $method = new ReflectionMethod(File::class, 'fillAttribute');
        $result = $method->invoke($this, $request, $requestAttribute, $model, $attribute);

        if ($previousFileName && ! empty($previousFileName)) {
            Storage::disk($this->disk)->delete($previousFileName);
            $displayCandidate = ImageStorageLayout::displayPathFor($previousFileName);
            if ($displayCandidate !== $previousFileName) {
                Storage::disk($this->disk)->delete($displayCandidate);
            }
        }

        return $result;
    }
}
