<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\InvestigationTheme;
use App\Models\Post;
use App\Models\PostTypes\OnlineMessage;
use App\Services\Images\ImageFormatPolicy;
use App\Services\Images\ImageIngestService;
use App\Services\Images\ImageStorageLayout;
use App\Services\Images\ImageType;
use Illuminate\Console\Command;

/**
 * Create display JPEG renditions for existing originals that need conversion (webp/avif),
 * and for all post covers. Does not rewrite DB paths.
 */
class GenerateDisplayImages extends Command
{
    protected $signature = 'images:generate-display
                            {--dry-run : Report only}
                            {--limit=0 : Max entities per type (0 = all)}
                            {--type= : post_cover|content_image|online_image|user_photo|theme}';

    protected $description = 'Generate display JPEG renditions without changing DB image paths';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $typeFilter = $this->option('type');

        $ingest = app(ImageIngestService::class);
        $created = 0;
        $skipped = 0;
        $failed = 0;

        $runType = function (string $label, ImageType $type, iterable $items, callable $pathGetter, callable $langGetter) use ($dryRun, $limit, $ingest, &$created, &$skipped, &$failed) {
            $this->info("=== {$label} ===");
            $n = 0;
            foreach ($items as $item) {
                if ($limit > 0 && $n >= $limit) {
                    break;
                }
                $n++;

                $path = $pathGetter($item);
                $lang = $langGetter($item);
                if ($path === null || $path === '') {
                    $skipped++;
                    continue;
                }

                $needs = $type->alwaysNeedsDisplay() || ImageFormatPolicy::needsConversion($path);
                if (! $needs) {
                    $skipped++;
                    continue;
                }

                $display = ImageStorageLayout::displayPathFor($path);
                if (ImageStorageLayout::exists($display, $lang)) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("[dry-run] would create {$display} from {$path}");
                    $created++;
                    continue;
                }

                $result = $ingest->renditionFor($path, $type, $lang);
                if ($result) {
                    $created++;
                } else {
                    $failed++;
                    $this->warn("Failed: {$path}");
                }
            }
        };

        if ($typeFilter === null || $typeFilter === ImageType::PostCover->value) {
            $query = Post::query()->whereNotNull('image')->where('image', '!=', '');
            $runType(
                'post covers',
                ImageType::PostCover,
                $query->lazyById(),
                fn ($p) => $p->image,
                fn ($p) => $p->language_code,
            );
        }

        if ($typeFilter === null || $typeFilter === ImageType::UserPhoto->value) {
            $query = Author::query()->whereNotNull('avatar')->where('avatar', '!=', '');
            $runType(
                'author avatars',
                ImageType::UserPhoto,
                $query->lazyById(),
                fn ($a) => $a->avatar,
                fn ($a) => $a->language_code,
            );
        }

        if ($typeFilter === null || $typeFilter === ImageType::ThemeCover->value) {
            $query = InvestigationTheme::query()->whereNotNull('cover_image')->where('cover_image', '!=', '');
            $runType(
                'theme covers',
                ImageType::ThemeCover,
                $query->lazyById(),
                fn ($t) => $t->cover_image,
                fn ($t) => $t->language_code,
            );
        }

        if ($typeFilter === null || $typeFilter === ImageType::ContentImage->value) {
            $this->info('=== content gallery images ===');
            $n = 0;
            Post::query()->whereNotNull('content')->orderBy('id')->chunkById(100, function ($posts) use ($dryRun, $limit, $ingest, &$created, &$skipped, &$failed, &$n) {
                foreach ($posts as $post) {
                    $blocks = is_array($post->content) ? $post->content : [];
                    foreach ($blocks as $block) {
                        if (($block['type'] ?? '') !== 'images') {
                            continue;
                        }
                        $images = $block['attributes']['images'] ?? [];
                        if (isset($images['link'])) {
                            $images = [$images];
                        }
                        foreach ($images as $image) {
                            if ($limit > 0 && $n >= $limit) {
                                return false;
                            }
                            $path = $image['link'] ?? null;
                            if (! $path || ! ImageFormatPolicy::needsConversion($path)) {
                                $skipped++;
                                continue;
                            }
                            $n++;
                            $display = ImageStorageLayout::displayPathFor($path);
                            if (ImageStorageLayout::exists($display, $post->language_code)) {
                                $skipped++;
                                continue;
                            }
                            if ($dryRun) {
                                $this->line("[dry-run] would create {$display}");
                                $created++;
                                continue;
                            }
                            $result = $ingest->renditionFor($path, ImageType::ContentImage, $post->language_code);
                            $result ? $created++ : $failed++;
                        }
                    }
                }
            });
        }

        if ($typeFilter === null || $typeFilter === ImageType::OnlineImage->value) {
            $this->info('=== online message images ===');
            $n = 0;
            OnlineMessage::query()->whereNotNull('images')->orderBy('id')->chunkById(100, function ($messages) use ($dryRun, $limit, $ingest, &$created, &$skipped, &$failed, &$n) {
                foreach ($messages as $message) {
                    $images = $message->images;
                    if (! is_array($images)) {
                        continue;
                    }
                    if (isset($images['link'])) {
                        $images = [$images];
                    }
                    $lang = $message->online?->language_code ?? 'ru';
                    foreach ($images as $image) {
                        if ($limit > 0 && $n >= $limit) {
                            return false;
                        }
                        $path = is_array($image) ? ($image['link'] ?? null) : null;
                        if (! $path || ! ImageFormatPolicy::needsConversion($path)) {
                            $skipped++;
                            continue;
                        }
                        $n++;
                        $display = ImageStorageLayout::displayPathFor($path);
                        if (ImageStorageLayout::exists($display, $lang)) {
                            $skipped++;
                            continue;
                        }
                        if ($dryRun) {
                            $this->line("[dry-run] would create {$display}");
                            $created++;
                            continue;
                        }
                        $result = $ingest->renditionFor($path, ImageType::OnlineImage, $lang);
                        $result ? $created++ : $failed++;
                    }
                }
            });
        }

        $this->info("Created: {$created}; skipped: {$skipped}; failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
