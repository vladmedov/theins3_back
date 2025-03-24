<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\PostAuthor;
use App\Models\PostHistory;

use App\Http\Controllers\PostController;
use App\Http\Controllers\MainPageController;
use App\Http\Controllers\CategoryController;

use App\Http\Controllers\TestController;

Route::get('/test', [TestController::class, 'test']);

Route::get('/', function (Request $request) {

    $user = auth()->user();
    $user->roles = ['admin'];
    $user->save();
    dd($user->toArray());

    // $p = Post::find(67);
    // $t = $p->authors()->orderBy('post_authors.position')->pluck('user_id')->toArray();
    // dd($t);die;

    // // PostAuthor::whereNot('post_id', 1000000)->delete();
    // // $postAuthor = new PostAuthor();
    // // $postAuthor->post_id = 67;
    // // $postAuthor->user_id = 2;
    // // $postAuthor->save();

    // // $a = PostAuthor::find(61);
    // // $a->position = 3;
    // // $a->save();

    // $a = PostAuthor::whereNot('post_id', 1000000)->get();
    // dd($a->toArray());


    dd([
        'session_id' => $request->session()->getId(),
        'locale' => $request->session()->get('locale', ''),
        'current_locale' => App::currentLocale(),
        'config_app_locale' => Config::get('app.locale'),
    ]);
    //return view('welcome');
});

Route::get('posts/{id}', function (Request $request, int $id) {
    $post = Post::with([
        'translation',
        'category',
        'tags',
        'owners',
        'authors',
        'columnist',
        'investigationTheme',
        'collections'
    ])->find($id);

    $post->incrementViewsCount();

    if (!$post) {
        return response()->json(['error' => 'Post not found'], 404);
    }

    dd($post->toArray());
});

Route::get('posts/{id}/history', function (Request $request, int $id) {
    // Получаем пост вместе со всеми связями
    $post = Post::with([
        'translation',
        'category',
        'tags',
        'owners',
        'authors',
        'columnist',
        'investigationTheme',
        'collections'
    ])->find($id);

    // Проверка существования поста
    if (!$post) {
        return response()->json(['error' => 'Post not found'], 404);
    }

    // Получаем историю изменений поста
    $history = PostHistory::where('post_id', $id)
        ->with('user') // Получаем пользователя, который сделал изменения
        ->orderBy('created_at', 'desc') // Сортируем по дате изменений (от последнего к первому)
        ->get();

    // Форматируем историю для вывода в JSON
    $formattedHistory = $history->map(function ($item) {
        return [
            'id' => $item->id,
            'status' => $item->status,
            'user' => optional($item->user)->name,
            'changes' => json_decode($item->changes, true),
            'created_at' => $item->created_at->format('d.m.Y H:i:s')
        ];
    });

    dd($formattedHistory->toArray());
});

Route::get('/set-locale/{locale}', function (Request $request, string $locale) {
    if (!in_array($locale, ['en', 'ru'])) {
        abort(400);
    }
    
    $request->session()->put('locale', $locale);
    App::setLocale($locale);

    return redirect('/nova');
    
    // $previousUrl = url()->previous();

    // if (Str::contains($previousUrl, ['nova-api/scripts', 'nova-api/styles'])) {
    //     return redirect('/nova');
    // }

    // return redirect()->to($previousUrl);
});