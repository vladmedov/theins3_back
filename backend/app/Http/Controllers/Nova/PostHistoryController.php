<?php

namespace App\Http\Controllers\Nova;

use App\Models\Post;
use App\Models\PostHistory;

use App\Http\Controllers\Controller;

class PostHistoryController extends Controller
{
    public function index($postId)
    {
        $post = Post::findOrFail($postId);

        $changes = PostHistory::where('post_id', $postId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'status', 'created_at']);

        return response()->json([
            'post' => $post->title,
            'changes' => $changes
        ]);
    }

    public function getChange($postId, $changeId)
    {
        $change = PostHistory::with('user')
            ->where('post_id', $postId)
            ->where('id', $changeId)
            ->firstOrFail();

        return response()->json([
            'id' => $change->id,
            'post_id' => $change->post_id,
            'status' => $change->status,
            'changes' => $change->changes,
            'user_fullname' => $change->user->fullname,
        ]);
    }
}
