<?php

namespace App\Console\Commands;

use App\Enums\PostTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportGallerySingleObjectPayloads extends Command
{
    protected $signature = 'gallery:report-single-object-payloads
                            {--sample=20 : How many example rows to print per section}
                            {--post-id= : Limit scan to this post id (posts.content + its online_messages)}';

    protected $description = 'Find legacy gallery JSON: single object {link:…} instead of a list of images';

    public function handle(): int
    {
        $sample = max(0, (int) $this->option('sample'));
        $onlyPostId = $this->option('post-id');
        $onlyPostId = ($onlyPostId === null || $onlyPostId === '') ? null : (int) $onlyPostId;

        $this->scanOnlineMessages($sample, $onlyPostId);
        $this->newLine();
        $this->scanPostContentImagesBlocks($sample, $onlyPostId);

        return self::SUCCESS;
    }

    private function scanOnlineMessages(int $sample, ?int $onlyPostId): void
    {
        $this->info('=== online_messages.images (single JSON object, not an array of items) ===');

        $hits = [];

        $q = DB::table('online_messages')
            ->select(['id', 'post_id', 'images'])
            ->whereNotNull('images')
            ->orderBy('id');

        if ($onlyPostId !== null) {
            $q->where('post_id', $onlyPostId);
        }

        $q->chunkById(500, function ($rows) use (&$hits) {
            foreach ($rows as $row) {
                $images = $this->decodedJson($row->images);
                if (! is_array($images) || $images === []) {
                    continue;
                }
                if (! array_is_list($images) && isset($images['link'])) {
                    $hits[] = [(int) $row->id, (int) $row->post_id];
                }
            }
        }, 'id');

        $this->line('Count: '.count($hits));
        if ($sample > 0 && $hits !== []) {
            foreach (array_slice($hits, 0, $sample) as [$id, $postId]) {
                $this->line("  online_message id={$id} post_id={$postId}");
            }
        }
    }

    private function scanPostContentImagesBlocks(int $sample, ?int $onlyPostId): void
    {
        $this->info('=== posts.content — blocks type=images with single-object attributes.images ===');

        $hits = [];

        $q = DB::table('posts')
            ->select(['id', 'slug', 'type', 'content'])
            ->where('type', '!=', PostTypes::ONLINE)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('content', '!=', '{}')
            ->orderBy('id');

        if ($onlyPostId !== null) {
            $q->where('id', $onlyPostId);
        }

        $q->chunkById(200, function ($rows) use (&$hits) {
            foreach ($rows as $row) {
                $content = json_decode($row->content, true);
                if (! is_array($content) || $content === []) {
                    continue;
                }
                foreach ($content as $blockKey => $block) {
                    if (! is_array($block) || ($block['type'] ?? '') !== 'images') {
                        continue;
                    }
                    $attrs = $block['attributes'] ?? null;
                    if (! is_array($attrs)) {
                        continue;
                    }
                    $images = $attrs['images'] ?? null;
                    if (! is_array($images)) {
                        continue;
                    }
                    if (! array_is_list($images) && isset($images['link'])) {
                        $hits[] = [
                            'id' => (int) $row->id,
                            'slug' => (string) ($row->slug ?? ''),
                            'type' => (string) ($row->type ?? ''),
                            'block_key' => is_string($blockKey) ? $blockKey : (string) $blockKey,
                        ];
                    }
                }
            }
        }, 'id');

        $this->line('Count: '.count($hits));
        if ($sample > 0 && $hits !== []) {
            foreach (array_slice($hits, 0, $sample) as $h) {
                $this->line(
                    "  post id={$h['id']} slug={$h['slug']} type={$h['type']} block_key={$h['block_key']}"
                );
            }
        }
    }

    private function decodedJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        return json_decode($value, true);
    }
}
