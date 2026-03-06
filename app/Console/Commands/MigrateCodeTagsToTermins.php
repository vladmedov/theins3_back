<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Termin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCodeTagsToTermins extends Command
{
    protected $signature = 'posts:migrate-code-tags
                            {--dry-run : Show what would change without saving}
                            {--id=* : Process specific post IDs only}';

    protected $description = 'Replace legacy <code>word</code> tags in post content with <span class="termin" data-id="X"> format';

    private int $updatedPosts   = 0;
    private int $replacedTags   = 0;
    private array $notFound     = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $ids    = $this->option('id');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        $query = Post::query();
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $total = $query->count();
        $this->info("Processing {$total} posts...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($posts) use ($dryRun, $bar) {
            foreach ($posts as $post) {
                $this->processPost($post, $dryRun);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Posts updated : {$this->updatedPosts}");
        $this->info("Tags replaced : {$this->replacedTags}");

        if (!empty($this->notFound)) {
            $unique = array_unique($this->notFound);
            sort($unique);
            $this->warn(count($unique) . ' unique term(s) inside <code> had no match in termins table (left unchanged):');
            foreach ($unique as $word) {
                $this->line("  - {$word}");
            }
        }

        return Command::SUCCESS;
    }

    private function processPost(Post $post, bool $dryRun): void
    {
        $raw = DB::table('posts')->where('id', $post->id)->value('content');
        if (empty($raw)) {
            return;
        }

        $blocks  = json_decode($raw, true) ?? [];
        $changed = false;
        $replacedInPost = 0;

        foreach ($blocks as $key => $block) {
            if (($block['type'] ?? '') !== 'text') {
                continue;
            }

            $html    = $block['attributes']['text'] ?? '';
            $newHtml = $this->replaceCodeTags($html, $replacedInPost, $post->language_code);

            if ($newHtml !== $html) {
                $blocks[$key]['attributes']['text'] = $newHtml;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $this->updatedPosts++;
        $this->replacedTags += $replacedInPost;

        if ($dryRun) {
            return;
        }

        DB::table('posts')->where('id', $post->id)->update([
            'content' => json_encode($blocks),
        ]);

        // Sync post_termins pivot from the updated content
        $terminIds = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                preg_match_all('/data-id="(\d+)"/', $block['attributes']['text'] ?? '', $m);
                $terminIds = array_merge($terminIds, $m[1]);
            }
        }
        $post->termins()->syncWithoutDetaching(array_unique(array_map('intval', $terminIds)));
    }

    private function replaceCodeTags(string $html, int &$count, string $languageCode): string
    {
        return preg_replace_callback(
            '/<code[^>]*>(.*?)<\/code>/is',
            function (array $matches) use (&$count, $languageCode): string {
                $displayWord = trim(strip_tags($matches[1]));
                $displayWord = preg_replace('/\s+/', ' ', $displayWord);

                if ($displayWord === '') {
                    return $matches[0];
                }

                $termin = Termin::where('termin', $displayWord)
                    ->where('language_code', $languageCode)
                    ->first();

                if (!$termin) {
                    $this->notFound[] = $displayWord;
                    return $matches[0];
                }

                $count++;
                return '<span class="termin" data-id="' . $termin->id . '">' . e($displayWord) . '</span>';
            },
            $html
        );
    }
}
