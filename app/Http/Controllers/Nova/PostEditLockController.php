<?php

namespace App\Http\Controllers\Nova;

use App\Services\Nova\PostEditLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Nova\Nova;

class PostEditLockController extends Controller
{
    public function __construct(
        protected PostEditLockService $postEditLockService
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_key' => ['required', 'string', 'max:512'],
            'client_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $clientVersion = $request->input('client_version');

        $payload = $this->postEditLockService->heartbeat(
            $validated['post_key'],
            $request->user(),
            $clientVersion === null || $clientVersion === '' ? null : (int) $clientVersion
        );

        return response()->json($payload);
    }

    public function takeover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_key' => ['required', 'string', 'max:512'],
        ]);

        $lock = $this->postEditLockService->takeover(
            $validated['post_key'],
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'lock' => $lock,
        ]);
    }

    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_key' => ['required', 'string', 'max:512'],
        ]);

        $postKey = $validated['post_key'];
        $parts = explode(':', $postKey, 2);
        $resourceUri = $parts[0] ?? '';
        if ($resourceUri === '' || ! isset($parts[1]) || $parts[1] === '') {
            abort(422, 'Invalid post key');
        }

        if (! $this->postEditLockService->releaseIfHeldByCurrentUser($postKey, $request->user())) {
            return response()->json([
                'ok' => false,
                'message' => __('post_edit_lock.cannot_release_not_holder'),
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'redirect' => Nova::url('/resources/'.$resourceUri),
        ]);
    }
}
