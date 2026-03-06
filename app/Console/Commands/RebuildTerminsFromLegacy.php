<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Standalone command — does NOT extend SyncFromLegacyDb.
 *
 * For every post that exists in legacy:
 *  1. Fetches legacy Text blocks + associated term definitions
 *  2. Rebuilds the text blocks in our DB using SyncFromLegacyDb::syncPostContent()
 *     (identical transformation: termin spans, h3 promotion, template expansion,
 *      HTML normalisation, post_termins pivot sync)
 *
 * syncPostContent() is accessed via Reflection to avoid inheritance issues.
 */
class RebuildTerminsFromLegacy extends Command
{
    protected $signature = 'termins:rebuild-from-legacy
                            {--dry-run : Preview without making any changes}
                            {--post=   : Process a single post by ID}
                            {--region= : Limit to a legacy region ID (1 or 3)}';

    protected $description = 'Delete all Termins / post_termins and rebuild text blocks from legacy DB';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $legacyDb = DB::connection('legacy_pgsql');
        $dryRun   = (bool) $this->option('dry-run');
        $postId   = $this->option('post');
        $regionId = $this->option('region');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        // ── Confirm + clear ───────────────────────────────────────────────────
        if (!$dryRun) {
            $cntT = DB::table('termins')->count();
            $cntP = DB::table('post_termins')->count();

            if (!$this->confirm(
                "This will DELETE {$cntT} Termin(s) and {$cntP} post_termins row(s), then rebuild all text blocks. Continue?"
            )) {
                return self::FAILURE;
            }

            $this->info('Clearing post_termins…');
            DB::statement('DELETE FROM post_termins');

            $this->info('Clearing termins…');
            DB::statement('DELETE FROM termins');
        }

        // ── Build legacy ID query (lazy — no full load) ───────────────────────
        if ($postId) {
            $legacyIdQuery = "SELECT ? AS regionable_id";
            $legacyIdBindings = [(int) $postId];
        } elseif ($regionId) {
            $legacyIdQuery = "SELECT regionable_id FROM public.region_relations
                               WHERE region_id = ? AND regionable_type = 'Post'
                               ORDER BY regionable_id";
            $legacyIdBindings = [(int) $regionId];
        } else {
            $legacyIdQuery = "SELECT DISTINCT regionable_id FROM public.region_relations
                               WHERE regionable_type = 'Post'
                               ORDER BY regionable_id";
            $legacyIdBindings = [];
        }

        $total = $legacyDb->selectOne(
            "SELECT COUNT(*) AS cnt FROM ({$legacyIdQuery}) t",
            $legacyIdBindings
        )->cnt ?? 0;

        $this->info("Legacy posts found: {$total}. Processing in chunks…");

        if ($total === 0) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        // ── Prepare SyncFromLegacyDb::syncPostContent() via Reflection ────────
        $syncCmd   = new SyncFromLegacyDb();
        $refMethod = new \ReflectionMethod(SyncFromLegacyDb::class, 'syncPostContent');
        $refMethod->setAccessible(true);

        // ── Stream IDs from legacy in chunks of 200 ───────────────────────────
        $bar       = $this->output->createProgressBar((int) $total);
        $processed = 0;
        $skipped   = 0;
        $errors    = 0;
        $offset    = 0;
        $chunkSize = 200;

        $bar->start();

        do {
            $rows = $legacyDb->select(
                "SELECT regionable_id FROM ({$legacyIdQuery}) t
                  ORDER BY regionable_id
                  LIMIT {$chunkSize} OFFSET {$offset}",
                $legacyIdBindings
            );

            if (empty($rows)) {
                break;
            }

            $chunkIds = array_column($rows, 'regionable_id');

            // Resolve language_code from our DB (only for IDs that exist here)
            $langMap = DB::table('posts')
                ->whereIn('id', $chunkIds)
                ->pluck('language_code', 'id');

            foreach ($chunkIds as $id) {
                $bar->advance();

                if (!isset($langMap[$id])) {
                    $skipped++;
                    continue;
                }

                try {
                    if (!$dryRun) {
                        $refMethod->invoke($syncCmd, $id, $langMap[$id]);
                    }
                    $processed++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Illuminate\Support\Facades\Log::warning(
                        "termins:rebuild — skipped post #{$id}: " . $e->getMessage()
                    );
                }
            }

            $offset += $chunkSize;
        } while (count($rows) === $chunkSize);

        $bar->finish();
        $this->newLine(2);

        $this->info("Processed : {$processed}");
        $this->info("Skipped   : {$skipped} (not in our DB)");
        if ($errors > 0) {
            $this->warn("Errors    : {$errors} (see laravel.log)");
        }

        if (!$dryRun) {
            $this->info('Termins created     : ' . DB::table('termins')->count());
            $this->info('post_termins created: ' . DB::table('post_termins')->count());
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
