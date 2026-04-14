<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\TerminSpanPublicTransformer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePostTerminSpansToHints extends Command
{
    protected $signature = 'termins:migrate-post-content-to-hints
                            {--dry-run : Report how many posts would change without saving}';

    protected $description = 'Replace span.termin data-id with data-description (base64) in post text blocks using current termins table';

    public function handle(TerminSpanPublicTransformer $transformer): int
    {
        $dry = (bool) $this->option('dry-run');
        $wouldChange = 0;
        $saved = 0;

        Post::query()->orderBy('id')->chunkById(100, function ($posts) use ($transformer, $dry, &$wouldChange, &$saved) {
            foreach ($posts as $post) {
                $content = $post->content;
                if (! is_array($content) || $content === []) {
                    continue;
                }
                $changed = false;
                foreach ($content as $key => $block) {
                    if (($block['type'] ?? '') !== 'text') {
                        continue;
                    }
                    $html = $block['attributes']['text'] ?? '';
                    if ($html === '' || ! str_contains($html, 'data-id')) {
                        continue;
                    }
                    $newHtml = $transformer->transformTextHtml($html);
                    if ($newHtml !== $html) {
                        $content[$key]['attributes']['text'] = $newHtml;
                        $changed = true;
                    }
                }
                if (! $changed) {
                    continue;
                }
                $wouldChange++;
                if ($dry) {
                    $this->line("Would update post id={$post->id}");

                    continue;
                }
                // Post::content uses CompactFlexibleCast that expects Nova format on write.
                // We already have compact DB format here, so write raw JSON directly.
                DB::table('posts')
                    ->where('id', $post->id)
                    ->update([
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                    ]);
                $saved++;
                $this->line("Updated post id={$post->id}");
            }
        });

        if ($dry) {
            $this->info("Dry run: {$wouldChange} post(s) would be updated.");
        } else {
            $this->info("Updated {$saved} post(s).");
        }

        return self::SUCCESS;
    }
}
