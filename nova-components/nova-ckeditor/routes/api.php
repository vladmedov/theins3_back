<?php

use Illuminate\Support\Facades\Route;

Route::get('/termins',  [\App\Http\Controllers\Nova\TerminSearchController::class, 'search']);
Route::post('/termins', [\App\Http\Controllers\Nova\TerminSearchController::class, 'store']);
