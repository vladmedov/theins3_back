<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Laravel Nova
Route::get('/set-locale/{locale}', function (Request $request, string $locale) {
    $request->session()->put('locale', $locale);
    App::setLocale($locale);

    $path = rtrim((string) config('nova.path'), '/');
    $url = $path === '' ? '/' : $path;

    return redirect($url.'?'.http_build_query(['nova_reset_sidebar_menu' => '1']));
})->where('locale', 'ru|en');

Route::middleware(['auth', 'throttle:nova-post-edit-lock'])
    ->prefix(rtrim(config('nova.path'), '/'))
    ->group(base_path('routes/nova-post-edit-lock.php'));
