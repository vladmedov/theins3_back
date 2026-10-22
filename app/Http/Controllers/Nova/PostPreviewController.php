<?php

namespace App\Http\Controllers\Nova;

use App\Models\Post;
use App\Services\PostPreviewTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PostPreviewController extends Controller
{
    public function redirect(Request $request, Post $post, PostPreviewTokenService $previewTokens): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if ($post->status !== Post::STATUS_DRAFT) {
            abort(404);
        }

        $hasAccess = $user->isAdmin()
            || $user->isEditor()
            || $post->isOwner($user->id);
        if (!$hasAccess) {
            abort(403);
        }

        $post->loadMissing('category', 'columnist');
        if (!$post->category) {
            abort(404);
        }

        $code = $previewTokens->createExchangeCode($post, (int) $user->id, $request->ip());

        $baseUrl = $post->language_code === 'ru'
            ? config('app.ru_edition_host')
            : config('app.en_edition_host');

        $target = rtrim((string) $baseUrl, '/').$post->getPath();
        $separator = str_contains($target, '?') ? '&' : '?';
        $target .= $separator.http_build_query(['preview_code' => $code]);

        return redirect()->away($target);
    }
}
