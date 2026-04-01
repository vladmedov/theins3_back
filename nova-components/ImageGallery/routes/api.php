<?php

use Illuminate\Http\Request;
use App\Services\ImageService;

Route::post('/upload-image', function (Request $request) {
    $request->validate([
        'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:20480',
    ]);

    $imageId = uniqid('', true);
    $file = $request->file('file');
    $languageCode = app()->getLocale();
    $disk = ImageService::publicDiskForLanguage($languageCode);

    $targetDir = ImageService::getImagePath($imageId, ImageService::TYPE_CONTENT_IMAGE, ImageService::SIZE_ORIGINAL);
    $path = $file->store($targetDir, $disk);

    ImageService::createImageVariants($imageId, $path, ImageService::TYPE_CONTENT_IMAGE, $languageCode);

    return response()->json([
        'id' => $imageId,
        'link' => $path,
    ]);
});
