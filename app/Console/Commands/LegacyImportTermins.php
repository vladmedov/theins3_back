<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Termin;

/**
 * Builds (or rebuilds) a persistent md5 cache that maps each unique termin
 * description to its canonical name and any already-existing Termin IDs.
 *
 * Cache key:   legacy_termin:md5:{md5_of_normalized_description}
 * Cache entry: ['termin' => string, 'description' => string, 'ids' => int[]]
 *
 * The "normalized" description is the raw description with every character
 * that is not a letter or digit stripped out before hashing — this makes
 * the lookup immune to punctuation / whitespace differences.
 *
 * Run this command once before running legacy:import_main so that
 * legacy:import_main can resolve termins from the cache instead of doing
 * per-record DB queries.
 */
class LegacyImportTermins extends Command
{
    protected $signature = 'legacy:import_termins
                            {--force : Clear existing cache entries and rebuild from scratch}';

    protected $description = 'Build md5 cache of legacy termin descriptions for use by legacy:import_main';

    private const CACHE_PREFIX = 'legacy_termin:md5:';
    private const CACHE_TTL_DAYS = 30;

    protected $legacy_db;

    public function __construct()
    {
        parent::__construct();
        $this->legacy_db = DB::connection('legacy_pgsql');
    }

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $force = (bool) $this->option('force');

        $this->info('Building legacy termin md5 cache' . ($force ? ' (forced rebuild)' : '') . '...');

        // ------------------------------------------------------------------
        // Step 1: Load all term content blocks from legacy.
        //         Each block carries a human_id like "{{term_abc}}" and a
        //         JSON content field whose "text" key holds the description.
        // ------------------------------------------------------------------
        $this->info('  Loading term blocks from legacy...');

        $termBlocks = $this->legacy_db->select("
            SELECT human_id, content
            FROM public.content_blocks
            WHERE kind = 'term'
        ");

        // human_id => raw description
        $descriptionByHumanId = [];
        // md5 => ['termin' => '', 'description' => '']  (termin name filled in step 3)
        $entriesToCache = [];

        foreach ($termBlocks as $block) {
            if (empty($block->human_id)) {
                continue;
            }

            $decoded     = json_decode($block->content ?? '{}');
            $description = $decoded->text ?? '';

            if ($description === '') {
                continue;
            }

            $descriptionByHumanId[$block->human_id] = $description;

            $md5 = $this->descriptionMd5($description);

            // If --force is not set, skip entries that are already in cache.
            if (!$force && Cache::has(self::CACHE_PREFIX . $md5)) {
                continue;
            }

            if (!isset($entriesToCache[$md5])) {
                $entriesToCache[$md5] = [
                    'termin'      => '',
                    'description' => $description,
                    'ids'         => [],
                ];
            }
        }

        $this->info(sprintf(
            '  Found %d term block(s), %d unique md5(s) to process.',
            count($descriptionByHumanId),
            count($entriesToCache)
        ));

        // ------------------------------------------------------------------
        // Step 2: Scan text content blocks that contain term references so we
        //         can populate the "termin" (display word) field in each
        //         cache entry. We only fetch blocks that actually contain a
        //         term placeholder to keep the dataset manageable.
        // ------------------------------------------------------------------
        $this->info('  Scanning text blocks for display words...');

        // Count total matching text blocks for the progress bar.
        $totalTextBlocks = (int) $this->legacy_db->selectOne("
            SELECT COUNT(*) AS cnt
            FROM public.content_blocks
            WHERE kind = 'text'
              AND POSITION('{{term_' IN content::text) > 0
        ")->cnt;

        $bar = $this->output->createProgressBar($totalTextBlocks);
        $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%%  scanning text blocks");
        $bar->setBarWidth(30);
        $bar->start();

        // Map: human_id => first display word encountered
        $displayWordByHumanId = [];
        $offset               = 0;
        $chunkSize            = 500;

        do {
            $textBlocks = $this->legacy_db->select("
                SELECT content
                FROM public.content_blocks
                WHERE kind = 'text'
                  AND POSITION('{{term_' IN content::text) > 0
                LIMIT {$chunkSize} OFFSET {$offset}
            ");

            foreach ($textBlocks as $block) {
                $decoded = json_decode($block->content ?? '{}');
                $text    = $decoded->text ?? '';

                if ($text !== '') {
                    preg_match_all(
                        '/<a\s+href="(\{\{term_[^}]+\}\})"[^>]*>(.*?)<\/a\s*>/is',
                        $text,
                        $matches,
                        PREG_SET_ORDER
                    );

                    foreach ($matches as $match) {
                        $humanId     = $match[1];
                        $displayWord = trim(strip_tags($match[2]));
                        $displayWord = preg_replace('/\s+/', ' ', $displayWord);

                        if ($displayWord !== '' && !isset($displayWordByHumanId[$humanId])) {
                            $displayWordByHumanId[$humanId] = $displayWord;
                        }
                    }
                }

                $bar->advance();
            }

            $offset += $chunkSize;
        } while (count($textBlocks) === $chunkSize);

        $bar->finish();
        $this->newLine();

        // ------------------------------------------------------------------
        // Step 3: Populate the "termin" field in each pending cache entry
        //         using the display words we just collected.
        // ------------------------------------------------------------------
        foreach ($descriptionByHumanId as $humanId => $description) {
            $md5 = $this->descriptionMd5($description);

            if (isset($entriesToCache[$md5]) && $entriesToCache[$md5]['termin'] === '') {
                $entriesToCache[$md5]['termin'] = $displayWordByHumanId[$humanId] ?? '';
            }
        }

        // ------------------------------------------------------------------
        // Step 4: Cross-reference with existing Termin records in the new DB
        //         so that already-imported termins get their IDs pre-populated.
        // ------------------------------------------------------------------
        $this->info('  Cross-referencing existing Termin records...');

        $existingTermins = Termin::select(['id', 'description'])->get();

        foreach ($existingTermins as $termin) {
            $md5 = $this->descriptionMd5($termin->description);

            // Update entries that are being (re-)cached.
            if (isset($entriesToCache[$md5])) {
                if (!in_array($termin->id, $entriesToCache[$md5]['ids'], true)) {
                    $entriesToCache[$md5]['ids'][] = $termin->id;
                }
                continue;
            }

            // For entries already in cache (not being rebuilt), augment IDs.
            if (!$force) {
                $cacheKey = self::CACHE_PREFIX . $md5;
                $existing = Cache::get($cacheKey);
                if ($existing !== null && !in_array($termin->id, $existing['ids'], true)) {
                    $existing['ids'][] = $termin->id;
                    Cache::put($cacheKey, $existing, now()->addDays(self::CACHE_TTL_DAYS));
                }
            }
        }

        // ------------------------------------------------------------------
        // Step 5: Write all new/rebuilt entries to cache.
        // ------------------------------------------------------------------
        $this->info('  Writing cache entries...');

        $written = 0;

        if (!empty($entriesToCache)) {
            $bar = $this->output->createProgressBar(count($entriesToCache));
            $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%%  writing cache");
            $bar->setBarWidth(30);
            $bar->start();

            foreach ($entriesToCache as $md5 => $entry) {
                Cache::put(self::CACHE_PREFIX . $md5, $entry, now()->addDays(self::CACHE_TTL_DAYS));
                $written++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $total = Termin::count();

        $this->info("Done. Written {$written} cache entry/entries. Existing Termins in DB: {$total}.");

        return 0;
    }

    /**
     * Compute md5 of a description with only alphanumeric characters kept.
     */
    public static function descriptionMd5(string $description): string
    {
        // Keep all Unicode letters (Latin, Cyrillic, etc.) and digits; strip
        // everything else (HTML tags, whitespace, punctuation).  Using only
        // ASCII [a-zA-Z0-9] was wrong: Cyrillic characters were stripped,
        // making many different Russian descriptions hash to the same MD5.
        return md5(preg_replace('/[^\p{L}\p{N}]/u', '', $description));
    }
}
