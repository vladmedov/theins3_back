<?php

use App\Services\Images\ImageFormatPolicy;
use App\Services\Images\ImageIngestService;
use App\Services\Images\ImageType;
use Illuminate\Http\Request;

Route::post('/upload-image', function (Request $request) {
    $request->validate([
        'file' => ['required', ...ImageFormatPolicy::validationRules()],
        'storage_disk' => 'required|string|in:ru_public,en_public',
        'image_type' => 'nullable|string|in:content_image,online_image',
    ]);

    $file = $request->file('file');
    $disk = (string) $request->input('storage_disk');
    $languageCode = $disk === 'en_public' ? 'en' : 'ru';
    $imageTypeValue = (string) $request->input('image_type', ImageType::ContentImage->value);
    $imageType = ImageType::from($imageTypeValue);
    $imageId = uniqid('', true);

    $stored = app(ImageIngestService::class)->ingest(
        $file,
        $imageType,
        $languageCode,
        $imageId,
    );

    if ($stored->width <= 0 || $stored->height <= 0) {
        return response()->json([
            'message' => 'Unable to determine image dimensions.',
        ], 422);
    }

    return response()->json([
        'id' => $imageId,
        'link' => $stored->originalPath,
        'width' => $stored->width,
        'height' => $stored->height,
    ]);
});
