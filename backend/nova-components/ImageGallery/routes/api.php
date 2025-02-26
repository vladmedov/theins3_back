<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::post('/upload-image', function (Request $request) {
    $request->validate([
        'file' => 'required|image|max:2048',
    ]);

    // Сохраняем файл в `storage/app/public/gallery/`
    $path = $request->file('file')->store('gallery', 'public');

    return response()->json([
        'link' => asset("storage/{$path}")
    ]);
});
