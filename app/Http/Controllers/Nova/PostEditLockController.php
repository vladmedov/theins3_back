<?php

namespace App\Http\Controllers\Nova;

use App\Services\Nova\PostEditLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
}
