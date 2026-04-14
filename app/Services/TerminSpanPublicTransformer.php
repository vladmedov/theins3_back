<?php

namespace App\Services;

use App\Models\Termin;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;

/**
 * Expands {@code <span class="termin" data-id="…">} to {@code data-description} (base64 per
 * {@see TerminDescriptionAttributeCodec}) for public JSON. Leaves spans that already only have
 * {@code data-description} unchanged.
 */
final class TerminSpanPublicTransformer
{
    public function transformContentBlocks(array $blocks): array
    {
        foreach ($blocks as $key => $block) {
            if (($block['type'] ?? '') !== 'text') {
                continue;
            }
            $text = $block['attributes']['text'] ?? '';
            if ($text === '' || ! str_contains($text, 'data-id')) {
                continue;
            }
            $blocks[$key]['attributes']['text'] = $this->transformTextHtml($text);
        }

        return $blocks;
    }

    public function transformTextHtml(string $html): string
    {
        if ($html === '' || ! str_contains($html, 'data-id')) {
            return $html;
        }

        preg_match_all('/data-id="(\d+)"/', $html, $matches);
        $ids = array_unique(array_map('intval', $matches[1]));
        if ($ids === []) {
            return $html;
        }

        /** @var Collection<int, Termin> $termins */
        $termins = Termin::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $this->replaceDataIdSpansInHtml($html, $termins);
    }

    private function replaceDataIdSpansInHtml(string $html, Collection $termins): string
    {
        try {
            $dom = new DOMDocument;
            libxml_use_internal_errors(true);
            $wrapped = '<div id="termin-span-wrap">'.$html.'</div>';
            $dom->loadHTML('<?xml encoding="UTF-8">'.$wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $nodes = $xpath->query("//span[contains(concat(' ', normalize-space(@class), ' '), ' termin ') and @data-id]");

            if ($nodes === false) {
                return $html;
            }

            foreach ($nodes as $span) {
                if (! $span instanceof DOMElement) {
                    continue;
                }
                $id = (int) $span->getAttribute('data-id');
                $description = $termins->get($id)?->description ?? '';
                $span->removeAttribute('data-id');
                $span->setAttribute('data-description', TerminDescriptionAttributeCodec::encode($description));
            }

            $wrap = $dom->getElementById('termin-span-wrap');
            if (! $wrap) {
                return $html;
            }

            $out = '';
            foreach ($wrap->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }

            return $out;
        } catch (\Throwable) {
            return $html;
        }
    }
}
