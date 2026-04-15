<?php

namespace App\Console\Commands;

use App\Enums\PostTypes;
use App\Services\ImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillGalleryImageDimensions extends Command
{
    protected $signature = 'gallery:fill-image-dimensions
                            {--dry-run : Show counts without writing}
                            {--post-id= : Process only this post id (gallery content, online messages, cover)}
                            {--chunk=100 : Chunk size for posts query}
                            {--touch-updated-at : Set updated_at when updating posts}';

    protected $description = 'Backfill width/height: gallery in posts.content (non-online), online_messages.images, and post cover (posts.image_width/height)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $postId = $this->option('post-id');
        $postId = ($postId === null || $postId === '') ? null : (int) $postId;
        $chunk = max(1, (int) $this->option('chunk'));
        $touchUpdatedAt = (bool) $this->option('touch-updated-at');

        $postsUpdated = 0;
        $messagesUpdated = 0;
        $coversUpdated = 0;
        $postsSkipped = 0;
        $messagesSkipped = 0;
        $coversSkipped = 0;

        $postsQuery = DB::table('posts')
            ->where('type', '!=', PostTypes::ONLINE)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('content', '!=', '{}')
            ->orderBy('id');

        if ($postId !== null) {
            $postsQuery->where('id', $postId);
        }

        $messagesQuery = DB::table('online_messages')
            ->join('posts', 'posts.id', '=', 'online_messages.post_id')
            ->select([
                'online_messages.id',
                'online_messages.images',
                'posts.language_code',
            ])
            ->whereNotNull('online_messages.images')
            ->whereRaw("online_messages.images::text != '[]'")
            ->whereRaw("online_messages.images::text != 'null'")
            ->orderBy('online_messages.id');

        if ($postId !== null) {
            $messagesQuery->where('online_messages.post_id', $postId);
        }

        $coversQuery = DB::table('posts')
            ->select(['id', 'image', 'language_code'])
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->where(function ($q) {
                $q->whereNull('image_width')->orWhereNull('image_height');
            })
            ->orderBy('id');

        if ($postId !== null) {
            $coversQuery->where('id', $postId);
        }

        $postsTotal = (clone $postsQuery)->count();
        $messagesTotal = (clone $messagesQuery)->count();
        $coversTotal = (clone $coversQuery)->count();
        $totalSteps = $postsTotal + $messagesTotal + $coversTotal;

        $bar = null;
        if ($totalSteps > 0) {
            $bar = $this->output->createProgressBar($totalSteps);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $bar->setMessage('posts');
            $bar->start();
        } else {
            $this->comment('No rows to scan (gallery content, online_messages images, or cover dimensions).');
        }

        $postsQuery->chunkById($chunk, function ($rows) use (
            $dryRun,
            $touchUpdatedAt,
            &$postsUpdated,
            &$postsSkipped,
            $bar
        ) {
            foreach ($rows as $row) {
                try {
                    $content = json_decode($row->content, true);
                    if (!is_array($content) || $content === []) {
                        $postsSkipped++;

                        continue;
                    }

                    $changed = false;
                    foreach ($content as $blockKey => $block) {
                        if (!is_array($block) || ($block['type'] ?? '') !== 'images') {
                            continue;
                        }
                        $attrs = $block['attributes'] ?? null;
                        if (!is_array($attrs) || empty($attrs['images'])) {
                            continue;
                        }
                        $blockChanged = false;
                        $newImages = $this->enrichGalleryImages($attrs['images'], $row->language_code, $blockChanged);
                        if ($blockChanged) {
                            $content[$blockKey]['attributes']['images'] = $newImages;
                            $changed = true;
                        }
                    }

                    if (!$changed) {
                        $postsSkipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $postsUpdated++;

                        continue;
                    }

                    $update = [
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ];
                    if ($touchUpdatedAt) {
                        $update['updated_at'] = now();
                    }

                    DB::table('posts')->where('id', $row->id)->update($update);
                    $postsUpdated++;
                } finally {
                    if ($bar !== null) {
                        $bar->advance();
                    }
                }
            }
        });

        if ($bar !== null) {
            $bar->setMessage('online_messages');
        }

        $messagesQuery->chunk($chunk, function ($rows) use (
            $dryRun,
            &$messagesUpdated,
            &$messagesSkipped,
            $bar
        ) {
            foreach ($rows as $row) {
                try {
                    $images = json_decode($row->images, true);
                    if (!is_array($images) || $images === []) {
                        $messagesSkipped++;

                        continue;
                    }

                    $changed = false;
                    $newImages = $this->enrichGalleryImages($images, $row->language_code, $changed);
                    if (!$changed) {
                        $messagesSkipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $messagesUpdated++;

                        continue;
                    }

                    DB::table('online_messages')->where('id', $row->id)->update([
                        'images' => json_encode($newImages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]);
                    $messagesUpdated++;
                } finally {
                    if ($bar !== null) {
                        $bar->advance();
                    }
                }
            }
        });

        if ($bar !== null) {
            $bar->setMessage('post covers');
        }

        $coversQuery->chunkById($chunk, function ($rows) use (
            $dryRun,
            $touchUpdatedAt,
            &$coversUpdated,
            &$coversSkipped,
            $bar
        ) {
            foreach ($rows as $row) {
                try {
                    $languageCode = $row->language_code ?? 'ru';
                    $dims = ImageService::getImageDimensions($row->image, $languageCode);
                    if ($dims === null) {
                        $coversSkipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $coversUpdated++;

                        continue;
                    }

                    $update = [
                        'image_width' => $dims['width'],
                        'image_height' => $dims['height'],
                    ];
                    if ($touchUpdatedAt) {
                        $update['updated_at'] = now();
                    }

                    DB::table('posts')->where('id', $row->id)->update($update);
                    $coversUpdated++;
                } finally {
                    if ($bar !== null) {
                        $bar->advance();
                    }
                }
            }
        }, 'id');

        if ($bar !== null) {
            $bar->finish();
            $this->newLine(2);
        }

        $this->info("Posts updated (gallery in content): {$postsUpdated}" . ($dryRun ? ' (dry-run)' : ''));
        $this->info("Online messages updated: {$messagesUpdated}" . ($dryRun ? ' (dry-run)' : ''));
        $this->info("Post covers updated (image_width/height): {$coversUpdated}" . ($dryRun ? ' (dry-run)' : ''));
        $this->info("Posts skipped (gallery, no change): {$postsSkipped}");
        $this->info("Messages skipped (no change): {$messagesSkipped}");
        $this->info("Post covers skipped (no file / unreadable): {$coversSkipped}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $images
     * @return list<array<string, mixed>>
     */
    private function enrichGalleryImages(array $images, string $languageCode, bool &$changed): array
    {
        if (!array_is_list($images)) {
            return [];
        }
        $list = $images;

        foreach ($list as $i => $img) {
            if (!is_array($img)) {
                continue;
            }
            $link = $img['link'] ?? null;
            if ($link === null || $link === '' || !is_string($link)) {
                continue;
            }
            if (isset($img['width'], $img['height'])
                && (int) $img['width'] > 0
                && (int) $img['height'] > 0) {
                continue;
            }

            $dims = ImageService::getImageDimensions($link, $languageCode);
            if ($dims === null) {
                continue;
            }

            $list[$i]['width'] = $dims['width'];
            $list[$i]['height'] = $dims['height'];
            $changed = true;
        }

        return $list;
    }
}
