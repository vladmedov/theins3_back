<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageService;
use App\Models\SyncLog;
use App\Models\Post;
use App\Models\Termin;
use App\Console\Commands\Traits\LegacyImportHelpersTrait;

/**
 * Full legacy import command (version 2).
 *
 * Part A – Taxonomy
 *   Syncs users, categories, authors, investigation themes and tags.
 *   Images that already exist on disk are never re-downloaded.
 *
 * Part B – Posts (chunked, ID-ordered)
 *   1. Determines which legacy post IDs are genuinely new (not yet in DB).
 *   2. Creates new posts with full content sync.
 *   3. For already-existing posts: always updates views_count; performs a
 *      full content re-sync only when the legacy updated_at timestamp differs
 *      from the one stored in the local DB.
 *
 * Termins in text blocks are resolved via the md5 cache built by
 * legacy:import_termins.  Run that command first.
 */
class LegacyImportMain extends Command
{
    use LegacyImportHelpersTrait;

    protected $signature = 'legacy:import_main
                            {--reset-stuck : Reset stuck import processes}
                            {--post= : Restore / re-sync a single post by legacy ID}';

    protected $description = 'Import / sync data from legacy database (v2 with chunked post sync and md5 termin cache)';

    protected $legacy_db;

    private const LOCK_KEY              = 'legacy-import-main-lock';
    private const LOCK_TIMEOUT          = 180;
    private const ACTIVITY_CHECK_MINUTES = 1;
    private const POST_CHUNK_SIZE       = 2000;
    private const CACHE_PREFIX          = 'legacy_termin:md5:';
    private const CACHE_TTL_DAYS        = 30;

    /**
     * In-process cache to avoid repeated Cache::get() calls for the same md5.
     */
    protected array $terminCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->legacy_db = DB::connection('legacy_pgsql');
    }

    public function handle(): int
    {
        if ($this->option('reset-stuck')) {
            SyncLog::resetStuckProcesses(self::ACTIVITY_CHECK_MINUTES);
            Cache::lock(self::LOCK_KEY, self::LOCK_TIMEOUT)->forceRelease();
            $this->info('Stuck processes and locks have been reset');
            return 0;
        }

        $singlePostId = $this->option('post');
        if ($singlePostId !== null && $singlePostId !== '') {
            return $this->restoreSinglePost((int) $singlePostId);
        }

        $runningProcesses = SyncLog::where('status', 'running')
            ->where('updated_at', '>', now()->subMinutes(self::ACTIVITY_CHECK_MINUTES))
            ->count();

        if ($runningProcesses > 0) {
            $this->warn('Another import process is already running (detected via sync_logs)');
            Log::warning('legacy:import_main skipped: another active process detected');
            return 1;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TIMEOUT);

        if (!$lock->get()) {
            $this->warn('Another import process is already running (cache lock)');
            Log::warning('legacy:import_main skipped: cache lock is held');
            return 1;
        }

        try {
            ini_set('memory_limit', '5G');

            $this->info('Starting legacy:import_main...');
            Log::info('legacy:import_main started');

            // ----------------------------------------------------------------
            // Part A: Taxonomy
            // ----------------------------------------------------------------
            $this->syncAdmins();

            $this->syncCategories(1);
            $this->syncAuthors(1);
            $this->syncInvestigationThemes(1);

            $this->syncCategories(3);
            $this->syncAuthors(3);
            $this->syncInvestigationThemes(3);

            // ----------------------------------------------------------------
            // Part B: Posts (chunked, all languages in ID order)
            // ----------------------------------------------------------------
            $this->syncPosts();

            $this->syncCollectionsCleanup();

            $this->resetSequences();

            $this->info('Import completed successfully!');
            Log::info('legacy:import_main completed successfully');

            return 0;
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            Log::error('legacy:import_main failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        } finally {
            $lock->release();
        }
    }

    // -------------------------------------------------------------------------
    // Chunked post sync (new strategy)
    // -------------------------------------------------------------------------

    private function syncPosts(): void
    {
        $entityType = "import_posts";

        try {
            SyncLog::markAsRunning($entityType);

            $this->info("Importing posts (all languages, ordered by ID ascending)");

            // Load every post ID across all regions in a single query,
            // ordered by post ID so processing is globally sequential.
            $regionRows = $this->legacy_db->select("
                SELECT regionable_id AS post_id, region_id
                FROM public.region_relations
                WHERE regionable_type = 'Post'
                ORDER BY regionable_id ASC
            ");

            if (empty($regionRows)) {
                $this->info("  No posts found in legacy");
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $legacyIds      = array_column($regionRows, 'post_id');
            $regionByPostId = [];
            foreach ($regionRows as $row) {
                $regionByPostId[(int) $row->post_id] = (int) $row->region_id;
            }

            // Compare against ALL existing posts regardless of language.
            $existingIds = Post::pluck('id')->toArray();

            $newIds     = array_values(array_diff($legacyIds, $existingIds));
            $toCheckIds = array_values(array_intersect($legacyIds, $existingIds));

            $this->info(sprintf(
                '  Total legacy: %d | New: %d | Existing to check: %d',
                count($legacyIds),
                count($newIds),
                count($toCheckIds)
            ));

            $createdCount      = 0;
            $updatedCount      = 0;
            $viewsUpdatedCount = 0;

            // One-time preloads: fill in-memory caches used by relation helpers.
            $this->preloadTagCache();
            $this->preloadEntityIds();
            $this->preloadTerminCache();

            // One progress bar for the whole operation (new + existing).
            $bar = $this->progressBar(count($newIds) + count($toCheckIds));

            // ----------------------------------------------------------------
            // Pass 1: Create new posts (full sync)
            // ----------------------------------------------------------------
            foreach (array_chunk($newIds, self::POST_CHUNK_SIZE) as $chunk) {
                // Batch-load ALL legacy data for this chunk in ~9 queries
                $this->preloadChunkData($chunk);
                $posts = $this->fetchLegacyPostsChunk($chunk);

                foreach ($posts as $post) {
                    $regionId     = $regionByPostId[(int) $post->id] ?? 1;
                    $languageCode = $regionId === 1 ? 'ru' : 'en';

                    $bar->setMessage($this->barLabel("[NEW/{$languageCode}] #{$post->id} {$post->title}"));

                    $imagePath = $this->downloadLegacyImage(
                        $post->id,
                        $post->preview_image ?? $post->detail_image ?? null,
                        'post',
                        ImageService::TYPE_POST_COVER
                    );

                    // withoutEvents skips the saved-hook which would wastefully
                    // scan null content for termin data-id attributes.
                    Post::withoutEvents(fn () => Post::updateOrCreate(
                        ['id' => $post->id],
                        $this->buildPostAttributes($post, $languageCode, $imagePath)
                    ));
                    // Refresh the model cache entry after upsert
                    $this->postModelCache[$post->id] = Post::find($post->id);

                    $this->syncPostRelations($post, $regionId, $languageCode);
                    $createdCount++;
                    $bar->advance();
                }
            }

            // ----------------------------------------------------------------
            // Pass 2: Update existing posts
            // ----------------------------------------------------------------
            foreach (array_chunk($toCheckIds, self::POST_CHUNK_SIZE) as $chunk) {
                // Batch-load all legacy data; also populates $this->postModelCache
                // with existing Post models so syncPost* helpers skip Post::find().
                $this->preloadChunkData($chunk);
                $legacyPosts = $this->fetchLegacyPostsChunk($chunk);

                foreach ($legacyPosts as $post) {
                    $dbPost = $this->postModelCache[$post->id] ?? null;
                    if (!$dbPost) {
                        $bar->advance();
                        continue;
                    }

                    $regionId     = $regionByPostId[(int) $post->id] ?? 1;
                    $languageCode = $regionId === 1 ? 'ru' : 'en';

                    $legacyUpdatedAt = \Carbon\Carbon::parse($post->updated_at);
                    $contentChanged  = !$dbPost->updated_at->eq($legacyUpdatedAt);

                    if ($contentChanged) {
                        $bar->setMessage($this->barLabel("[UPD/{$languageCode}] #{$post->id} {$post->title}"));

                        $imagePath = $this->downloadLegacyImage(
                            $post->id,
                            $post->preview_image ?? $post->detail_image ?? null,
                            'post',
                            ImageService::TYPE_POST_COVER
                        );

                        Post::withoutEvents(fn () => Post::where('id', $post->id)->update(
                            $this->buildPostAttributes($post, $languageCode, $imagePath)
                        ));

                        $this->syncPostRelations($post, $regionId, $languageCode);
                        $updatedCount++;
                    } else {
                        $bar->setMessage($this->barLabel("[views/{$languageCode}] #{$post->id} {$post->title}"));

                        // Only refresh view count; preserve updated_at to keep the
                        // change-detection logic accurate on subsequent runs.
                        Post::where('id', $post->id)->update([
                            'views_count' => $post->viewed,
                            'updated_at'  => $dbPost->updated_at,
                        ]);
                        $viewsUpdatedCount++;
                    }

                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info(sprintf(
                '  Done: %d created, %d content-updated, %d views-only updated',
                $createdCount,
                $updatedCount,
                $viewsUpdatedCount
            ));

            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers for syncPosts
    // -------------------------------------------------------------------------

    /**
     * Fetch a chunk of posts from the legacy DB (with category join).
     */
    private function fetchLegacyPostsChunk(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', $ids);

        return $this->legacy_db->select("
            SELECT posts.*, rubric_relations.rubric_id
            FROM public.posts
            LEFT JOIN (
                SELECT rubricable_id, MIN(rubric_id) AS rubric_id
                FROM public.rubric_relations
                WHERE rubricable_type = 'Post'
                GROUP BY rubricable_id
            ) AS rubric_relations ON rubric_relations.rubricable_id = posts.id
            WHERE posts.id IN ({$placeholders})
            ORDER BY posts.id ASC
        ");
    }

    /**
     * Build the attribute array shared by insert and update operations.
     */
    private function buildPostAttributes(object $post, string $languageCode, ?string $imagePath): array
    {
        return [
            'language_code'    => $languageCode,
            'slug'             => $post->slug ?? $post->id,
            'type'             => match ($post->type) {
                'Post::News'    => 'news',
                'Post::Opinion' => 'opinion',
                'Post::Article' => 'article',
                'Post::Online'  => 'online',
                'Post::Card'    => 'article',
            },
            'category_id'      => $post->rubric_id,
            'title'            => $post->title,
            'status'           => $post->published ? 'published' : 'draft',
            'author_visibility' => match ($post->type) {
                'Post::News' => 'force_hidden',
                default      => 'default',
            },
            'image'            => $imagePath,
            'image_description' => $post->image_description,
            'published_at'     => $post->published_at,
            'created_at'       => $post->created_at,
            'updated_at'       => $post->updated_at,
            'lead'             => $this->cleanLead($post->lead),
            'is_super_news'    => $post->super_news,
            'views_count'      => $post->viewed,
        ];
    }

    /**
     * Sync all post relations (content, tags, authors, themes, admins, collections).
     */
    private function syncPostRelations(object $post, int $regionId, string $languageCode): void
    {
        if ($post->type === 'Post::Online') {
            $this->syncOnlineMessages($post->id, $languageCode);
        } else {
            $this->syncPostContent($post->id, $languageCode);
        }

        $this->syncPostTags($post->id, $languageCode);
        $this->syncPostAuthorsForPost($post->id);
        $this->syncThemePostForPost($post->id);
        $this->syncAdminRelationsForPost($post->id);
        $this->syncCollectionsForPost($post->id, $regionId, $languageCode);
    }

    // -------------------------------------------------------------------------
    // Lead cleanup
    // -------------------------------------------------------------------------

    /**
     * Strip cosmetic trailing/leading line-breaks from an HTML lead string.
     *
     * Removes patterns like:
     *   - <br>\n&nbsp; at the end of the last <p> before </p>
     *   - &nbsp;<br> at the beginning of a <p>
     *   - standalone &nbsp; lines that act as blank spacers
     */
    private function cleanLead(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // Remove <br> (optionally followed by whitespace / &nbsp;) before </p>
        $html = preg_replace('/(<br\s*\/?>)\s*(&nbsp;)?\s*(<\/p>)/i', '$3', $html);

        // Remove &nbsp; (optionally preceded by whitespace) before </p>
        $html = preg_replace('/\s*&nbsp;\s*(<\/p>)/i', '$1', $html);

        // Remove <br> (optionally preceded by whitespace / &nbsp;) after <p> or <p ...>
        $html = preg_replace('/(<p(?:\s[^>]*)?>)\s*(&nbsp;)?\s*(<br\s*\/?>)\s*/i', '$1', $html);

        // Remove a leading &nbsp; right after <p> or <p ...>
        $html = preg_replace('/(<p(?:\s[^>]*)?>)\s*&nbsp;\s*/i', '$1', $html);

        return trim($html);
    }

    // -------------------------------------------------------------------------
    // Overrides from LegacyImportHelpersTrait
    // -------------------------------------------------------------------------

    /**
     * Skip download if the file already exists on disk; otherwise download normally.
     */
    protected function downloadLegacyImage(int $id, ?string $legacyFilename, string $legacySlug, string $imageType): ?string
    {
        if (empty($legacyFilename)) {
            return null;
        }

        $targetPath = ImageService::getImagePath($id, $imageType, ImageService::SIZE_ORIGINAL)
            . '/' . $legacyFilename;

        if (Storage::disk('public')->exists($targetPath)) {
            return $targetPath;
        }

        $url = 'https://insidertexts.com/storage/' . $legacySlug . '/' . $id . '/' . $legacyFilename;

        try {
            Storage::disk('public')->makeDirectory(dirname($targetPath));

            $fullPath = Storage::disk('public')->path($targetPath);

            $response = Http::timeout(30)
                ->withOptions(['sink' => $fullPath])
                ->get($url);

            if (!$response->successful()) {
                @unlink($fullPath);
                Log::warning("Не удалось загрузить {$imageType} #{$id}: HTTP {$response->status()}");
                return null;
            }

            return $targetPath;
        } catch (\Exception $e) {
            Log::warning("Ошибка загрузки {$imageType} #{$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve a Termin using the md5 cache built by legacy:import_termins.
     *
     * Falls back to the default DB-based lookup when no cache entry is found
     * so the command can run even if legacy:import_termins was not executed.
     */
    protected function resolveTermin(string $displayWord, string $terminDescription, Post $post): ?Termin
    {
        $md5      = LegacyImportTermins::descriptionMd5($terminDescription);
        $cacheKey = self::CACHE_PREFIX . $md5;

        // Lazy-load from persistent cache into the in-process array.
        if (!array_key_exists($md5, $this->terminCache)) {
            $this->terminCache[$md5] = Cache::get($cacheKey);
        }

        $entry  = $this->terminCache[$md5];
        $termin = null;

        // Try to reuse an already-imported Termin.
        // Use the in-memory terminById cache (no DB hit) when available;
        // fall back to Termin::find() only if the cache was not preloaded.
        if ($entry !== null && !empty($entry['ids'])) {
            $validIds = [];
            foreach ($entry['ids'] as $candidateId) {
                $candidate = $this->terminById[$candidateId]
                    ?? (!empty($this->terminById) ? null : Termin::find($candidateId));
                if (
                    $candidate
                    && $candidate->language_code === $post->language_code
                    && LegacyImportTermins::descriptionMd5($candidate->description) === $md5
                ) {
                    $termin   = $candidate;
                    $validIds = [$candidateId];
                    break;
                }
            }

            if (count($validIds) !== count($entry['ids'])) {
                $entry['ids'] = $validIds;
                Cache::put($cacheKey, $entry, now()->addDays(self::CACHE_TTL_DAYS));
                $this->terminCache[$md5] = $entry;
            }
        }

        if (!$termin) {
            $name   = ($entry['termin'] ?? '') ?: $displayWord;
            $termin = Termin::create([
                'language_code' => $post->language_code,
                'termin'        => Termin::uniqueName($name),
                'description'   => $terminDescription,
            ]);
            // Keep in-memory cache warm so subsequent calls in this run find it.
            $this->terminById[$termin->id] = $termin;

            // Persist the new ID back so subsequent blocks reuse the same record.
            if ($entry === null) {
                $entry = [
                    'termin'      => $displayWord,
                    'description' => $terminDescription,
                    'ids'         => [],
                ];
            }
            $entry['ids'][] = $termin->id;
            Cache::put($cacheKey, $entry, now()->addDays(self::CACHE_TTL_DAYS));
            $this->terminCache[$md5] = $entry;
        }

        return $termin;
    }
}
