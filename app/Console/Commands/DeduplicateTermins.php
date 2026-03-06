<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateTermins extends Command
{
    protected $signature = 'termins:deduplicate
                            {--dry-run : Show what would change without saving}';

    protected $description = 'Deduplicate Termin records by description+language_code and fix all post references';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        // ── Step 1: find duplicate groups ────────────────────────────────────
        $this->info('Step 1: Finding duplicates by description + language_code...');

        $groups = DB::table('termins')
            ->select('description', 'language_code', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(id) as canonical_id'))
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->groupBy('description', 'language_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicates found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$groups->count()} duplicate group(s).");

        // Build mapping: old_id => canonical_id
        $idMap    = [];
        $toDelete = [];

        foreach ($groups as $group) {
            $ids = DB::table('termins')
                ->where('description', $group->description)
                ->where('language_code', $group->language_code)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

            $canonicalId = array_shift($ids);

            foreach ($ids as $oldId) {
                $idMap[$oldId] = $canonicalId;
                $toDelete[]    = $oldId;
            }

            $preview = mb_substr($group->description, 0, 50);
            $this->line(sprintf(
                '  [%s] «%s» — keep #%d, remove: #%s',
                $group->language_code,
                $preview,
                $canonicalId,
                implode(', #', $ids),
            ));
        }

        $this->info(sprintf(
            '%d Termin(s) to remove, %d reference(s) to remap.',
            count($toDelete),
            count($idMap),
        ));

        // ── Step 2: update post content ───────────────────────────────────────
        $this->info('Step 2: Updating post content (span data-id)...');

        $postsUpdated = 0;

        DB::table('posts')
            ->whereNotNull('content')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($idMap, $dryRun, &$postsUpdated) {
                foreach ($rows as $row) {
                    $blocks  = json_decode($row->content, true) ?? [];
                    $changed = false;

                    foreach ($blocks as &$block) {
                        if (($block['type'] ?? '') !== 'text') {
                            continue;
                        }

                        $html = $block['attributes']['text'] ?? '';

                        foreach ($idMap as $oldId => $canonicalId) {
                            $new = str_replace(
                                'data-id="' . $oldId . '"',
                                'data-id="' . $canonicalId . '"',
                                $html,
                            );
                            if ($new !== $html) {
                                $html    = $new;
                                $changed = true;
                            }
                        }

                        $block['attributes']['text'] = $html;
                    }
                    unset($block);

                    if ($changed) {
                        $postsUpdated++;
                        if (!$dryRun) {
                            DB::table('posts')
                                ->where('id', $row->id)
                                ->update(['content' => json_encode($blocks)]);
                        }
                    }
                }
            });

        $this->info("Posts updated: {$postsUpdated}");

        // ── Step 3: update post_termins pivot ─────────────────────────────────
        $this->info('Step 3: Updating post_termins pivot...');

        $pivotProcessed = 0;

        foreach ($idMap as $oldId => $canonicalId) {
            $pivotRows = DB::table('post_termins')->where('termin_id', $oldId)->get();

            foreach ($pivotRows as $pivotRow) {
                $pivotProcessed++;

                if ($dryRun) {
                    continue;
                }

                $alreadyHasCanonical = DB::table('post_termins')
                    ->where('post_id', $pivotRow->post_id)
                    ->where('termin_id', $canonicalId)
                    ->exists();

                if ($alreadyHasCanonical) {
                    DB::table('post_termins')
                        ->where('post_id', $pivotRow->post_id)
                        ->where('termin_id', $oldId)
                        ->delete();
                } else {
                    DB::table('post_termins')
                        ->where('post_id', $pivotRow->post_id)
                        ->where('termin_id', $oldId)
                        ->update(['termin_id' => $canonicalId]);
                }
            }
        }

        $this->info("Pivot rows processed: {$pivotProcessed}");

        // ── Step 4: delete duplicate Termins ─────────────────────────────────
        $this->info('Step 4: Deleting duplicate Termins...');

        if (!$dryRun) {
            DB::table('termins')->whereIn('id', $toDelete)->delete();
        }

        $this->info('Termins deleted: ' . count($toDelete));
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
