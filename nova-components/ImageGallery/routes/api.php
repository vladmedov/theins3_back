<?php

use Illuminate\Http\Request;
use App\Services\ImageService;

Route::post('/upload-image', function (Request $request) {
    $request->validate([
        'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:20480',
        'storage_disk' => 'required|string|in:ru_public,en_public',
        'image_type' => 'nullable|string|in:content_image,online_image',
    ]);

    $imageId = uniqid('', true);
    $file = $request->file('file');
    $disk = (string) $request->input('storage_disk');
    $languageCode = $disk === 'en_public' ? 'en' : 'ru';
    $imageType = (string) $request->input('image_type', ImageService::TYPE_CONTENT_IMAGE);

    $targetDir = ImageService::getImagePath($imageId, $imageType, ImageService::SIZE_ORIGINAL);
    $path = $file->store($targetDir, $disk);

    ImageService::createImageVariants($imageId, $path, $imageType, $languageCode);

    $dimensions = ImageService::getImageDimensions($path, $languageCode);
    if ($dimensions === null) {
        return response()->json([
            'message' => 'Unable to determine image dimensions.',
        ], 422);
    }

    return response()->json([
        'id' => $imageId,
        'link' => $path,
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
    ]);
});
