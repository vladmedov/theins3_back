<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// RSS Feeds
use App\Http\Controllers\FeedController;
Route::prefix('{language_code}/feed')->where(['language_code' => 'ru|en'])->group(function () {
    Route::get('/', [FeedController::class, 'rss']);
    Route::get('/yandex-news', [FeedController::class, 'yandexNews']);
    Route::get('/dzen', [FeedController::class, 'dzen']);
    Route::get('/google-news', [FeedController::class, 'googleNews']);
    Route::get('/facebook-instant', [FeedController::class, 'facebookInstant']);
});

// Laravel Nova
Route::get('/set-locale/{locale}', function (Request $request, string $locale) {
    $request->session()->put('locale', $locale);
    App::setLocale($locale);
    return redirect('/nova');
})->where('locale', 'ru|en');





// use App\Models\Post;
// use App\Models\PostAuthor;
// use App\Models\PostHistory;

// use App\Http\Controllers\PostController;
// use App\Http\Controllers\MainPageController;
// use App\Http\Controllers\CategoryController;

// use App\Http\Controllers\TestController;

// Route::get('/', function (Request $request) {
//     $user = auth()->user();
//     $user->roles = ['admin'];
//     $user->save();
//     dd($user->toArray());
//     dd([
//         'session_id' => $request->session()->getId(),
//         'locale' => $request->session()->get('locale', ''),
//         'current_locale' => App::currentLocale(),
//         'config_app_locale' => Config::get('app.locale'),
//     ]);
// });

// Route::get('posts/{id}', function (Request $request, int $id) {
//     $post = Post::with([
//         'translation',
//         'category',
//         'tags',
//         'owners',
//         'authors',
//         'columnist',
//         'investigationTheme',
//         'collections'
//     ])->find($id);

//     $post->incrementViewsCount();

//     if (!$post) {
//         return response()->json(['error' => 'Post not found'], 404);
//     }

//     dd($post->toArray());
// });

// Route::get('posts/{id}/history', function (Request $request, int $id) {
//     // Получаем пост вместе со всеми связями
//     $post = Post::with([
//         'translation',
//         'category',
//         'tags',
//         'owners',
//         'authors',
//         'columnist',
//         'investigationTheme',
//         'collections'
//     ])->find($id);

//     // Проверка существования поста
//     if (!$post) {
//         return response()->json(['error' => 'Post not found'], 404);
//     }

//     // Получаем историю изменений поста
//     $history = PostHistory::where('post_id', $id)
//         ->with('user') // Получаем пользователя, который сделал изменения
//         ->orderBy('created_at', 'desc') // Сортируем по дате изменений (от последнего к первому)
//         ->get();

//     // Форматируем историю для вывода в JSON
//     $formattedHistory = $history->map(function ($item) {
//         return [
//             'id' => $item->id,
//             'status' => $item->status,
//             'user' => optional($item->user)->name,
//             'changes' => json_decode($item->changes, true),
//             'created_at' => $item->created_at->format('d.m.Y H:i:s')
//         ];
//     });

//     dd($formattedHistory->toArray());
// });