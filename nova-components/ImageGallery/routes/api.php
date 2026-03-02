<?php

use Illuminate\Http\Request;
use App\Services\ImageService;

Route::post('/upload-image', function (Request $request) {
    $request->validate([
        'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:20480',
    ]);

    $imageId = uniqid('', true);
    $file = $request->file('file');

    $targetDir = ImageService::getImagePath($imageId, ImageService::TYPE_CONTENT_IMAGE, ImageService::SIZE_ORIGINAL);
    $path = $file->store($targetDir, 'public');

    ImageService::createImageVariants($imageId, $path, ImageService::TYPE_CONTENT_IMAGE);

    return response()->json([
        'id' => $imageId,
        'link' => $path,
    ]);
});
