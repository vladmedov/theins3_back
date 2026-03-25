<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Публикует черновики с истекшим временем публикации и включённой авто-публикацией';

    public function handle(): int
    {
        $count = 0;

        Post::query()
            ->where('auto_publish_pending', true)
            ->where('status', Post::STATUS_DRAFT)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($posts) use (&$count) {
                foreach ($posts as $post) {
                    $post->auto_publish_pending = false;
                    $post->status = Post::STATUS_PUBLISHED;
                    $post->save();
                    $count++;
                }
            });

        if ($count > 0) {
            $this->info("Опубликовано постов: {$count}");
        }

        return self::SUCCESS;
    }
}
