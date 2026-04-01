<?php

use Illuminate\Http\Request;
use App\Services\ImageService;

Route::post('/upload-image', function (Request $request) {
    $request->validate([
        'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:20480',
        'storage_disk' => 'required|string|in:ru_public,en_public',
    ]);

    $imageId = uniqid('', true);
    $file = $request->file('file');
    $disk = (string) $request->input('storage_disk');
    $languageCode = $disk === 'en_public' ? 'en' : 'ru';

    $targetDir = ImageService::getImagePath($imageId, ImageService::TYPE_CONTENT_IMAGE, ImageService::SIZE_ORIGINAL);
    $path = $file->store($targetDir, $disk);

    ImageService::createImageVariants($imageId, $path, ImageService::TYPE_CONTENT_IMAGE, $languageCode);

    return response()->json([
        'id' => $imageId,
        'link' => $path,
    ]);
});
