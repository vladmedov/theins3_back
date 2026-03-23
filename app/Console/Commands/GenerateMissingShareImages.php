<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\ShareImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateMissingShareImages extends Command
{
    protected $signature = 'posts:generate-missing-share-images
                            {--limit=0 : Process only first N posts (0 = all)}
                            {--chunk=1000 : Chunk size}
                            {--id-from= : Process posts with id >= value}
                            {--id-to= : Process posts with id <= value}';

    protected $description = 'Generate share images only for posts where share file is missing';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(100, (int) $this->option('chunk'));
        $idFrom = $this->option('id-from');
        $idTo = $this->option('id-to');

        $idFrom = ($idFrom === null || $idFrom === '') ? null : (int) $idFrom;
        $idTo = ($idTo === null || $idTo === '') ? null : (int) $idTo;

        if ($idFrom !== null && $idTo !== null && $idFrom > $idTo) {
            $this->error('Option --id-from must be less than or equal to --id-to.');
            return self::FAILURE;
        }

        $query = Post::query()
            ->select(['id', 'image', 'language_code', 'published_at'])
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($idFrom !== null) {
            $query->where('id', '>=', $idFrom);
        }
        if ($idTo !== null) {
            $query->where('id', '<=', $idTo);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No posts with cover image found in local DB.');
            return self::SUCCESS;
        }
        if ($limit > 0) {
            $total = min($total, $limit);
            $query->limit($limit);
        }

        $processed = 0;
        $generated = 0;
        $alreadyExists = 0;
        $failed = 0;

        $this->info("Processing {$total} posts...");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%%  gen:%message%');
        $bar->setBarWidth(30);
        $bar->setMessage('0');
        $bar->start();

        foreach ($query->lazy($chunk) as $post) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $processed++;

            $sharePath = ShareImageService::getShareImagePath($post);
            if (Storage::disk('public')->exists($sharePath)) {
                $alreadyExists++;
            } else {
                $result = ShareImageService::generate($post);
                if ($result !== null) {
                    $generated++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed to generate share for post #{$post->id}");
                }
            }

            $bar->setMessage((string) $generated);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. Processed: {$processed}");
        $this->line("Generated: {$generated}");
        $this->line("Already exists: {$alreadyExists}");
        $this->line("Failed: {$failed}");

        return self::SUCCESS;
    }
}
