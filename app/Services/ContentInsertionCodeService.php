<?php

namespace App\Services;

/**
 * Expands insertion-code tags in text blocks (e.g. {{ images_idKEY }})
 * and hides blocks with show_insertion_code when they are only referenced in text.
 * Returns content in the same keyed format for the API (frontend unchanged).
 */
class ContentInsertionCodeService
{
    /** Block types that can be referenced by insertion code and hidden from their position when show_insertion_code is true */
    private const INSERTION_TYPES = ['images', 'video', 'embed', 'quote', 'related'];

    /**
     * @param array<string, array{type: string, attributes: array}> $content Key => block (type, attributes)
     * @return array<string, array{type: string, attributes: array}> Same format, with text blocks expanded and hidden blocks removed from flow
     */
    public function expand(array $content): array
    {
        if (empty($content)) {
            return $content;
        }

        $result = [];

        foreach ($content as $blockKey => $block) {
            $type = $block['type'] ?? '';
            $attributes = $block['attributes'] ?? [];

            // Blocks with show_insertion_code true are not rendered in their position; they only appear when referenced in text
            if ($this->isInsertionType($type) && ($attributes['show_insertion_code'] ?? false)) {
                continue;
            }

            if ($type === 'text') {
                $segments = $this->expandTextBlock($blockKey, $attributes['text'] ?? '', $content);
                foreach ($segments as $key => $segment) {
                    $result[$key] = $segment;
                }
                continue;
            }

            $result[$blockKey] = $block;
        }

        // Second pass: extract <h3 class="outline-heading">...</h3> from text blocks into outline blocks
        $result = $this->extractOutlineHeadings($result);

        return $result;
    }

    private function isInsertionType(string $type): bool
    {
        return in_array($type, self::INSERTION_TYPES, true);
    }

    /**
     * Split text by {{ type_idKEY }} tags and return ordered list of blocks (text fragments + referenced blocks).
     * When a tag is inside <p>...</p>, the whole paragraph is replaced by the block (no empty <p></p> left).
     *
     * @param array<string, array{type: string, attributes: array}> $content
     * @return array<string, array{type: string, attributes: array}>
     */
    private function expandTextBlock(string $textBlockKey, string $html, array $content): array
    {
        $pattern = '/\{\{\s*(\w+)_id([a-zA-Z0-9_-]+)\s*\}\}/';

        if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [$textBlockKey => [
                'type' => 'text',
                'attributes' => ['text' => $html],
            ]];
        }

        $result = [];
        $index = 0;
        $lastEnd = 0;

        foreach ($matches[0] as $i => $fullMatch) {
            $type = $matches[1][$i][0];
            $refKey = $matches[2][$i][0];
            $tagStart = $fullMatch[1];
            $tagLength = strlen($fullMatch[0]);
            $tagEnd = $tagStart + $tagLength;

            // Replace the whole enclosing <p>...</p> with the block so no empty paragraph remains
            [$segmentStart, $segmentEnd] = $this->findEnclosingParagraph($html, $tagStart, $tagEnd);

            // Text before this segment (paragraph or just the tag)
            $before = substr($html, $lastEnd, $segmentStart - $lastEnd);
            if (!$this->isBlankHtml($before)) {
                $result[$textBlockKey . '_' . $index] = [
                    'type' => 'text',
                    'attributes' => ['text' => $before],
                ];
                $index++;
            }

            // Referenced block: only substitute if exists, is same type, and has show_insertion_code true
            $refBlock = $content[$refKey] ?? null;
            if (
                $refBlock !== null
                && ($refBlock['type'] ?? '') === $type
                && ($refBlock['attributes']['show_insertion_code'] ?? false)
            ) {
                $result[$refKey] = $refBlock;
            } else {
                // Leave whole segment as literal (tag or paragraph containing tag)
                $segmentHtml = substr($html, $segmentStart, $segmentEnd - $segmentStart);
                if (!$this->isBlankHtml($segmentHtml)) {
                    $result[$textBlockKey . '_' . $index] = [
                        'type' => 'text',
                        'attributes' => ['text' => $segmentHtml],
                    ];
                    $index++;
                }
            }

            $lastEnd = $segmentEnd;
        }

        // Trailing text (skip if blank)
        $after = substr($html, $lastEnd);
        if (!$this->isBlankHtml($after)) {
            $result[$textBlockKey . '_' . $index] = [
                'type' => 'text',
                'attributes' => ['text' => $after],
            ];
        }

        return $result;
    }

    /**
     * Find the boundaries of the <p>...</p> that contains the given tag position (or the tag itself if not inside <p>).
     *
     * @return array{0: int, 1: int} [start, end] byte offsets; segment is html[start..end), end is after </p>
     */
    private function findEnclosingParagraph(string $html, int $tagStart, int $tagEnd): array
    {
        // Last opening <p...> before the tag
        if (preg_match_all('/<\s*p(\s[^>]*)?>/i', $html, $openMatches, PREG_OFFSET_CAPTURE) === false) {
            return [$tagStart, $tagEnd];
        }
        $paragraphStart = $tagStart;
        foreach ($openMatches[0] as $m) {
            $pos = $m[1];
            if ($pos < $tagStart) {
                $paragraphStart = $pos;
            }
        }
        if ($paragraphStart === $tagStart) {
            return [$tagStart, $tagEnd];
        }

        // First closing </p> after the tag
        $closePos = stripos($html, '</p>', $tagEnd);
        if ($closePos === false) {
            return [$tagStart, $tagEnd];
        }
        $paragraphEnd = $closePos + 4; // length of '</p>'

        return [$paragraphStart, $paragraphEnd];
    }

    /**
     * Extract <h3 class="outline-heading">...</h3> from text blocks and emit them as separate outline blocks.
     */
    private function extractOutlineHeadings(array $blocks): array
    {
        $outlinePattern = '/<h3\s[^>]*class=["\'][^"\']*\boutline-heading\b[^"\']*["\'][^>]*>(.*?)<\/h3>/is';
        $result = [];

        foreach ($blocks as $blockKey => $block) {
            if (($block['type'] ?? '') !== 'text') {
                $result[$blockKey] = $block;
                continue;
            }

            $html = $block['attributes']['text'] ?? '';

            if (!preg_match_all($outlinePattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                $result[$blockKey] = $block;
                continue;
            }

            $index = 0;
            $lastEnd = 0;

            foreach ($matches[0] as $i => $fullMatch) {
                $headingText = $matches[1][$i][0];
                $start = $fullMatch[1];
                $length = strlen($fullMatch[0]);

                $before = substr($html, $lastEnd, $start - $lastEnd);
                if (!$this->isBlankHtml($before)) {
                    $result[$blockKey . '_oh_' . $index] = [
                        'type' => 'text',
                        'attributes' => ['text' => $before],
                    ];
                    $index++;
                }

                $result[$blockKey . '_outline_' . $index] = [
                    'type' => 'outline',
                    'attributes' => ['outline' => trim(strip_tags($headingText))],
                ];
                $index++;

                $lastEnd = $start + $length;
            }

            $after = substr($html, $lastEnd);
            if (!$this->isBlankHtml($after)) {
                $result[$blockKey . '_oh_' . $index] = [
                    'type' => 'text',
                    'attributes' => ['text' => $after],
                ];
            }
        }

        return $result;
    }

    /**
     * True if the HTML segment has no visible content (empty, whitespace only, only empty <p></p>,
     * or only the opening/closing part of an empty paragraph e.g. "<p>\n    " or "\n</p>").
     */
    private function isBlankHtml(string $html): bool
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return true;
        }
        // Strip empty <p>...</p> (with optional attributes and whitespace inside)
        $prev = '';
        while ($prev !== $trimmed) {
            $prev = $trimmed;
            $trimmed = preg_replace('/<\s*p(\s[^>]*)?>\s*<\/\s*p\s*>/i', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }
        if ($trimmed === '') {
            return true;
        }
        // Only opening <p> and whitespace, or only closing </p> and whitespace (remnants of a paragraph that only contained the tag)
        if (preg_match('/^\s*<\s*p(\s[^>]*)?>\s*$/i', $trimmed) === 1) {
            return true;
        }
        if (preg_match('/^\s*<\/\s*p\s*>\s*$/i', $trimmed) === 1) {
            return true;
        }
        return false;
    }
}
