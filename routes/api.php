<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MainPageController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\InvestigationThemeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TrackingPixelController;
use App\Http\Controllers\MailchimpController;

Route::prefix('{language_code}')->group(function () {
    Route::get('/layout-data', [MainPageController::class, 'getLayoutData']);
    Route::get('/main-page', [MainPageController::class, 'getMainPage']);
    Route::get('/posts', [PostController::class, 'getAllExceptOpinions']);
    Route::get('/search', [SearchController::class, 'search']);

    Route::get('/investigations', [InvestigationThemeController::class, 'getInvestigationThemes']);
    Route::get('/tags', [TagController::class, 'getAllTags']);

    Route::get('/category/{slug}', [CategoryController::class, 'getCategory']);
    Route::get('/tag/{slug}', [TagController::class, 'getTag']);
    Route::get('/investigation/{slug}', [InvestigationThemeController::class, 'getInvestigationTheme']);
    Route::get('/author/{slug}', [UserController::class, 'getAuthor']);
    Route::get('/columnist/{slug}', [UserController::class, 'getColumnist']);

    Route::get('/post/{category_slug}/{slug}', [PostController::class, 'getPost']);
    Route::get('/post-preview', [PostController::class, 'getPostPreview']);
    
    Route::post('/track-view', [TrackingPixelController::class, 'trackView']);
    
    Route::post('/newsletter/subscribe', [MailchimpController::class, 'subscribe']);
});