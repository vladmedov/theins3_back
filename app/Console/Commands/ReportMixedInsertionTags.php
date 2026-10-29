<?php

namespace App\Console\Commands;

use App\Support\InsertionCode\InsertionTagPlacement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportMixedInsertionTags extends Command
{
    protected $signature = 'insertion-code:report-non-standalone-tags
                            {--post-id= : Limit scan to this post id}
                            {--only-resolvable : Only tags that match a block with show_insertion_code}
                            {--format=table : Output format: table or csv}
                            {--output= : Write CSV to this path (implies format=csv)}';

    protected $description = 'List posts where {{ insertion }} tags are outside a clean standalone <p> (mixed paragraph or outside <p>)';

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        $outputPath = $this->option('output');
        if (is_string($outputPath) && $outputPath !== '') {
            $format = 'csv';
        }
        if (! in_array($format, ['table', 'csv'], true)) {
            $this->error('Invalid --format; use table or csv.');

            return self::FAILURE;
        }

        $onlyPostId = $this->option('post-id');
        $onlyPostId = ($onlyPostId === null || $onlyPostId === '') ? null : (int) $onlyPostId;
        $onlyResolvable = (bool) $this->option('only-resolvable');

        $rows = [];
        $reasonCounts = [
            InsertionTagPlacement::REASON_MIXED_PARAGRAPH => 0,
            InsertionTagPlacement::REASON_OUTSIDE_PARAGRAPH => 0,
        ];
        $postIds = [];

        $q = DB::table('posts')
            ->select(['id', 'slug', 'title', 'type', 'content'])
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('content', '!=', '{}')
            ->orderBy('id');

        if ($onlyPostId !== null) {
            $q->where('id', $onlyPostId);
        }

        $q->chunkById(200, function ($posts) use (&$rows, &$reasonCounts, &$postIds, $onlyResolvable) {
            foreach ($posts as $post) {
                $content = json_decode((string) $post->content, true);
                if (! is_array($content) || $content === []) {
                    continue;
                }

                $resolvableTags = $onlyResolvable
                    ? $this->buildResolvableNormalizedTags($content)
                    : null;

                $hits = $this->scanContentBlocks($content, '', $resolvableTags);
                if ($hits === []) {
                    continue;
                }

                $postIds[(int) $post->id] = true;

                foreach ($hits as $hit) {
                    $reasonCounts[$hit['reason']] = ($reasonCounts[$hit['reason']] ?? 0) + 1;
                    $rows[] = [
                        'post_id' => (int) $post->id,
                        'slug' => (string) $post->slug,
                        'title' => (string) $post->title,
                        'type' => (string) $post->type,
                        'location' => $hit['location'],
                        'reason' => $hit['reason'],
                        'tag' => $hit['tag'],
                        'paragraph_preview' => $hit['paragraph_preview'] ?? '',
                    ];
                }
            }
        }, 'id');

        if ($format === 'csv') {
            $this->writeCsv($rows, is_string($outputPath) && $outputPath !== '' ? $outputPath : null);
        } else {
            if ($rows === []) {
                $this->info('No non-standalone insertion tags found.');
            } else {
                $this->table(
                    ['post_id', 'slug', 'title', 'type', 'location', 'reason', 'tag', 'paragraph_preview'],
                    array_map(static function (array $row): array {
                        return [
                            $row['post_id'],
                            mb_strimwidth($row['slug'], 0, 40, '…'),
                            mb_strimwidth($row['title'], 0, 40, '…'),
                            $row['type'],
                            mb_strimwidth($row['location'], 0, 40, '…'),
                            $row['reason'],
                            mb_strimwidth($row['tag'], 0, 40, '…'),
                            mb_strimwidth((string) $row['paragraph_preview'], 0, 50, '…'),
                        ];
                    }, $rows)
                );
            }
        }

        $this->newLine();
        $this->info('Summary');
        $this->line('  Posts affected: '.count($postIds));
        $this->line('  Hits total: '.count($rows));
        $this->line('  mixed_paragraph: '.($reasonCounts[InsertionTagPlacement::REASON_MIXED_PARAGRAPH] ?? 0));
        $this->line('  outside_paragraph: '.($reasonCounts[InsertionTagPlacement::REASON_OUTSIDE_PARAGRAPH] ?? 0));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{type?: string, attributes?: array}>  $content
     * @param  array<string, true>|null  $resolvableNormalizedTags
     * @return list<array{location: string, reason: string, tag: string, paragraph_preview: string|null}>
     */
    private function scanContentBlocks(array $content, string $locationPrefix, ?array $resolvableNormalizedTags): array
    {
        $hits = [];

        foreach ($content as $blockKey => $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $attrs = $block['attributes'] ?? [];
            $loc = $locationPrefix === ''
                ? (string) $blockKey
                : $locationPrefix.'.'.$blockKey;

            if ($type === 'text') {
                $html = (string) ($attrs['text'] ?? '');
                foreach (InsertionTagPlacement::scanHtml($html) as $hit) {
                    if ($resolvableNormalizedTags !== null && ! $this->isResolvableTag($hit['tag'], $resolvableNormalizedTags)) {
                        continue;
                    }
                    $hits[] = [
                        'location' => $loc,
                        'reason' => $hit['reason'],
                        'tag' => $hit['tag'],
                        'paragraph_preview' => $hit['paragraph_preview'],
                    ];
                }
                continue;
            }

            if ($type !== 'accordion') {
                continue;
            }

            $items = $attrs['items'] ?? null;
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemLoc = $loc.'[item '.$i.']';

                if (isset($item['blocks']) && is_array($item['blocks'])) {
                    // Structured blocks (list or keyed)
                    $itemHits = $this->scanContentBlocks(
                        $this->normalizeBlockList($item['blocks']),
                        $itemLoc,
                        $resolvableNormalizedTags
                    );
                    foreach ($itemHits as $hit) {
                        $hits[] = $hit;
                    }
                    continue;
                }

                $html = (string) ($item['content'] ?? '');
                foreach (InsertionTagPlacement::scanHtml($html) as $hit) {
                    if ($resolvableNormalizedTags !== null && ! $this->isResolvableTag($hit['tag'], $resolvableNormalizedTags)) {
                        continue;
                    }
                    $hits[] = [
                        'location' => $itemLoc.'.content',
                        'reason' => $hit['reason'],
                        'tag' => $hit['tag'],
                        'paragraph_preview' => $hit['paragraph_preview'],
                    ];
                }
            }
        }

        return $hits;
    }

    /**
     * @param  list<array{type?: string, attributes?: array}>|array<string, array{type?: string, attributes?: array}>  $blocks
     * @return array<string, array{type?: string, attributes?: array}>
     */
    private function normalizeBlockList(array $blocks): array
    {
        if ($blocks === []) {
            return [];
        }

        if (array_is_list($blocks)) {
            $keyed = [];
            foreach ($blocks as $i => $block) {
                $keyed['b'.$i] = $block;
            }

            return $keyed;
        }

        return $blocks;
    }

    /**
     * @param  array<string, array{type?: string, attributes?: array}>  $content
     * @return array<string, true>
     */
    private function buildResolvableNormalizedTags(array $content): array
    {
        $map = [];

        foreach ($content as $blockKey => $block) {
            if (! is_string($blockKey) || ! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $attrs = $block['attributes'] ?? [];

            if (! in_array($type, InsertionTagPlacement::INSERTION_TYPES, true)) {
                continue;
            }
            if (! $this->isTruthyInsertionFlag($attrs['show_insertion_code'] ?? false)) {
                continue;
            }

            $tag = '{{ '.$type.'_id'.$blockKey.' }}';
            $map[$this->normalizeTag($tag)] = true;
        }

        return $map;
    }

    /**
     * @param  array<string, true>  $resolvableNormalizedTags
     */
    private function isResolvableTag(string $tag, array $resolvableNormalizedTags): bool
    {
        return isset($resolvableNormalizedTags[$this->normalizeTag($tag)]);
    }

    private function normalizeTag(string $tag): string
    {
        $tag = InsertionTagPlacement::normalizeHtml($tag);
        $tag = preg_replace('/\s+/u', '', $tag) ?? $tag;

        return strtolower($tag);
    }

    private function isTruthyInsertionFlag(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));

            return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCsv(array $rows, ?string $outputPath): void
    {
        $headers = ['post_id', 'slug', 'title', 'type', 'location', 'reason', 'tag', 'paragraph_preview'];

        if ($outputPath === null) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                $this->error('Failed to open stdout for CSV.');

                return;
            }
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($h) => $row[$h] ?? '', $headers));
            }
            fclose($out);

            return;
        }

        $dir = dirname($outputPath);
        if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $out = fopen($outputPath, 'w');
        if ($out === false) {
            $this->error("Failed to write CSV to {$outputPath}");

            return;
        }

        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($h) => $row[$h] ?? '', $headers));
        }
        fclose($out);

        $this->info("CSV written to {$outputPath}");
    }
}
