<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Laravel Nova
Route::get('/set-locale/{locale}', function (Request $request, string $locale) {
    $request->session()->put('locale', $locale);
    App::setLocale($locale);

    return redirect(config('nova.path'));
})->where('locale', 'ru|en');

Route::middleware(['auth', 'throttle:nova-post-edit-lock'])
    ->prefix(rtrim(config('nova.path'), '/'))
    ->group(base_path('routes/nova-post-edit-lock.php'));
