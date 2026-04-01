<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\ImageService;
use App\Services\ShareImageService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class GenerateMissingShareImages extends Command
{
    protected $signature = 'posts:generate-missing-images
                            {--limit=0 : Process only first N posts (0 = all)}
                            {--chunk=1000 : Chunk size}
                            {--id-from= : Process posts with id >= value}
                            {--id-to= : Process posts with id <= value}
                            {--rebuild-share : Force rebuild share images and delete stale share files}
                            {--share-only : Process only share images (skip small/medium checks)}
                            {--cleanup-legacy-flat-share : After generation, remove legacy share/{prefix}/*.png files}';

    protected $description = 'Generate missing post images (small, medium, share) for local posts';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(100, (int) $this->option('chunk'));
        $idFrom = $this->option('id-from');
        $idTo = $this->option('id-to');
        $rebuildShare = (bool) $this->option('rebuild-share');
        $shareOnly = (bool) $this->option('share-only');
        $cleanupLegacyFlatShare = (bool) $this->option('cleanup-legacy-flat-share');

        $idFrom = ($idFrom === null || $idFrom === '') ? null : (int) $idFrom;
        $idTo = ($idTo === null || $idTo === '') ? null : (int) $idTo;

        if ($idFrom !== null && $idTo !== null && $idFrom > $idTo) {
            $this->error('Option --id-from must be less than or equal to --id-to.');
            return self::FAILURE;
        }

        $query = Post::query()
            ->select(['id', 'image', 'language_code', 'published_at'])
            ->whereNotNull('image')
            ->where('image', '!=', '');

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
        }

        $processed = 0;
        $generatedSmall = 0;
        $generatedMedium = 0;
        $generatedShare = 0;
        $existsSmall = 0;
        $existsMedium = 0;
        $existsShare = 0;
        $failed = 0;
        $missingOriginal = 0;
        $deletedLegacyFlatShare = 0;
        $prefixesWithGeneratedShareByDisk = [];

        $this->info("Processing {$total} posts...");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%%  fixed:%message%');
        $bar->setBarWidth(30);
        $bar->setMessage('0');
        $bar->start();

        foreach ($query->lazyByIdDesc($chunk, 'id') as $post) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $processed++;

            $disk = Storage::disk(ImageService::publicDiskForLanguage($post->language_code));

            if (!$disk->exists($post->image)) {
                $missingOriginal++;
                $failed++;
                $bar->setMessage((string) ($generatedSmall + $generatedMedium + $generatedShare));
                $bar->advance();
                continue;
            }

            if (!$shareOnly) {
                $filename = basename($post->image);
                $smallPath = ImageService::getImagePath($post->id, ImageService::TYPE_POST_COVER, ImageService::SIZE_SMALL)
                    . '/' . $filename;
                $mediumPath = ImageService::getImagePath($post->id, ImageService::TYPE_POST_COVER, ImageService::SIZE_MEDIUM)
                    . '/' . $filename;

                $needSmall = !$disk->exists($smallPath);
                $needMedium = !$disk->exists($mediumPath);

                if ($needSmall || $needMedium) {
                    ImageService::createImageVariants($post->id, $post->image, ImageService::TYPE_POST_COVER, $post->language_code);
                }

                if ($disk->exists($smallPath)) {
                    $needSmall ? $generatedSmall++ : $existsSmall++;
                } else {
                    $failed++;
                }

                if ($disk->exists($mediumPath)) {
                    $needMedium ? $generatedMedium++ : $existsMedium++;
                } else {
                    $failed++;
                }
            }

            $sharePath = ShareImageService::getShareImagePath($post);
            $shareExistsBefore = $disk->exists($sharePath);

            if ($rebuildShare || !$shareExistsBefore) {
                $result = ShareImageService::generate($post);
                if ($result !== null && $disk->exists($result)) {
                    $generatedShare++;
                    $diskName = ImageService::publicDiskForLanguage($post->language_code);
                    $prefix = (string) intdiv((int) $post->id, 1000);
                    $prefixesWithGeneratedShareByDisk[$diskName][$prefix] = true;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed to generate share for post #{$post->id}");
                }
            } else {
                $existsShare++;
            }

            $bar->setMessage((string) ($generatedSmall + $generatedMedium + $generatedShare));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($cleanupLegacyFlatShare) {
            foreach ($prefixesWithGeneratedShareByDisk as $diskName => $prefixMap) {
                $disk = Storage::disk($diskName);
                $deletedLegacyFlatShare += $this->deleteLegacyFlatShareFiles($disk, array_keys($prefixMap));
            }
        }

        $this->info("Done. Processed: {$processed}");
        $this->line("Generated small: {$generatedSmall}");
        $this->line("Generated medium: {$generatedMedium}");
        $this->line("Generated share: {$generatedShare}");
        $this->line("Already exists small: {$existsSmall}");
        $this->line("Already exists medium: {$existsMedium}");
        $this->line("Already exists share: {$existsShare}");
        $this->line("Missing original image: {$missingOriginal}");
        $this->line("Deleted legacy flat share files: {$deletedLegacyFlatShare}");
        $this->line("Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Delete legacy share files that are flat in prefix directory: share/{prefix}/*.png
     * (does not touch new nested paths share/{prefix}/{id}/{token}.png).
     */
    private function deleteLegacyFlatShareFiles(FilesystemAdapter $disk, array $prefixes): int
    {
        $deleted = 0;

        foreach ($prefixes as $prefix) {
            $directory = "share/{$prefix}";
            $directoryFsPath = $disk->path($directory);

            if (!is_dir($directoryFsPath)) {
                continue;
            }

            foreach (glob($directoryFsPath . '/*.png') ?: [] as $absolutePath) {
                $relativePath = $directory . '/' . basename($absolutePath);
                if ($disk->delete($relativePath)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
