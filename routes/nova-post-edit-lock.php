<?php

use App\Http\Controllers\Nova\PostEditLockController;
use Illuminate\Support\Facades\Route;

Route::post('post-edit-lock/heartbeat', [PostEditLockController::class, 'heartbeat'])
    ->name('nova.post-edit-lock.heartbeat');

Route::post('post-edit-lock/takeover', [PostEditLockController::class, 'takeover'])
    ->name('nova.post-edit-lock.takeover');

Route::post('post-edit-lock/release', [PostEditLockController::class, 'release'])
    ->name('nova.post-edit-lock.release');
