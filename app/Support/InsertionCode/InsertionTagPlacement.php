<?php

namespace App\Support\InsertionCode;

/**
 * Placement rules for insertion-code tags ({{ type_idKEY }}).
 * A tag is expanded only when it is the sole content of an enclosing <p>
 * (plus whitespace, &nbsp;, or <br>).
 */
class InsertionTagPlacement
{
    public const REASON_MIXED_PARAGRAPH = 'mixed_paragraph';

    public const REASON_OUTSIDE_PARAGRAPH = 'outside_paragraph';

    /** @var list<string> */
    public const INSERTION_TYPES = ['images', 'video', 'embed', 'quote', 'related', 'accordion', 'regions_map'];

    private const TAG_PATTERN = '/\{\{[\s\S]*?\}\}/u';

    /**
     * Decode entities (&#123; → {) so tags pasted/saved via CKEditor still match; strip invisible chars.
     */
    public static function normalizeHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $html) ?? $html;

        return $html;
    }

    /**
     * Find the boundaries of the <p>...</p> that contains the given tag position
     * (or the tag itself if not inside <p>).
     *
     * @return array{0: int, 1: int} [start, end] byte offsets; segment is html[start..end), end is after </p>
     */
    public static function findEnclosingParagraph(string $html, int $tagStart, int $tagEnd): array
    {
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

        $closePos = stripos($html, '</p>', $tagEnd);
        if ($closePos === false) {
            return [$tagStart, $tagEnd];
        }

        return [$paragraphStart, $closePos + 4];
    }

    public static function isInsideParagraph(string $html, int $tagStart, int $tagEnd): bool
    {
        [$paragraphStart, $paragraphEnd] = self::findEnclosingParagraph($html, $tagStart, $tagEnd);

        return $paragraphStart !== $tagStart || $paragraphEnd !== $tagEnd;
    }

    /**
     * True when paragraph content consists only of the target insertion tag and ignorable separators.
     * Ignorable separators: whitespace, &nbsp;, and <br>.
     */
    public static function isParagraphOnlyInsertionTag(string $paragraphHtml, string $fullTag): bool
    {
        $normalizedParagraph = self::normalizeHtml($paragraphHtml);
        $normalizedTag = self::normalizeHtml($fullTag);

        $inner = preg_replace('/^\s*<\s*p(\s[^>]*)?>/i', '', $normalizedParagraph, 1) ?? $normalizedParagraph;
        $inner = preg_replace('/<\/\s*p\s*>\s*$/i', '', $inner, 1) ?? $inner;

        if ($inner === '') {
            return false;
        }

        $inner = preg_replace('/' . preg_quote($normalizedTag, '/') . '/u', '', $inner, 1) ?? $inner;
        $inner = preg_replace('/(?:\s|&nbsp;|<br\s*\/?>)+/iu', '', $inner) ?? $inner;

        return $inner === '';
    }

    public static function isEligibleForExpansion(string $html, int $tagStart, int $tagEnd, string $fullTag): bool
    {
        if (! self::isInsideParagraph($html, $tagStart, $tagEnd)) {
            return false;
        }

        [$paragraphStart, $paragraphEnd] = self::findEnclosingParagraph($html, $tagStart, $tagEnd);
        $paragraphHtml = substr($html, $paragraphStart, $paragraphEnd - $paragraphStart);

        return self::isParagraphOnlyInsertionTag($paragraphHtml, $fullTag);
    }

    /**
     * Scan HTML for insertion tags that are not eligible for expansion.
     *
     * @param  list<string>|null  $insertionTypes  Limit to known insertion types (null = use INSERTION_TYPES)
     * @return list<array{tag: string, reason: string, paragraph_preview: string|null}>
     */
    public static function scanHtml(string $html, ?array $insertionTypes = null): array
    {
        $html = self::normalizeHtml($html);
        $types = $insertionTypes ?? self::INSERTION_TYPES;
        $typeAlt = implode('|', array_map(static fn (string $t): string => preg_quote($t, '/'), $types));
        $knownTagPattern = '/\{\{\s*(?:' . $typeAlt . ')_id[\s\S]*?\}\}/iu';

        if (! preg_match_all(self::TAG_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $hits = [];

        foreach ($matches[0] as $fullMatch) {
            $fullTag = $fullMatch[0];
            $tagStart = $fullMatch[1];
            $tagEnd = $tagStart + strlen($fullTag);

            if ($typeAlt !== '' && preg_match($knownTagPattern, $fullTag) !== 1) {
                continue;
            }

            if (self::isEligibleForExpansion($html, $tagStart, $tagEnd, $fullTag)) {
                continue;
            }

            $reason = self::REASON_OUTSIDE_PARAGRAPH;
            $paragraphPreview = null;

            if (self::isInsideParagraph($html, $tagStart, $tagEnd)) {
                $reason = self::REASON_MIXED_PARAGRAPH;
                [$paragraphStart, $paragraphEnd] = self::findEnclosingParagraph($html, $tagStart, $tagEnd);
                $paragraphPreview = self::truncate(substr($html, $paragraphStart, $paragraphEnd - $paragraphStart), 120);
            }

            $hits[] = [
                'tag' => self::truncate(preg_replace('/\s+/u', ' ', trim($fullTag)) ?? $fullTag, 80),
                'reason' => $reason,
                'paragraph_preview' => $paragraphPreview,
            ];
        }

        return $hits;
    }

    private static function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1) . '…';
    }
}
