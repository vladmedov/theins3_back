<?php

namespace App\Services;

use App\Models\Post;

class ContentRenderer
{
    public static function renderToHtml(Post $post): string
    {
        $content = $post->content;

        if (empty($content) || !is_array($content)) {
            return '';
        }

        $content = app(ContentInsertionCodeService::class)->expand($content);

        $html = '';

        foreach ($content as $block) {
            $type = $block['type'] ?? null;
            $attrs = $block['attributes'] ?? [];

            $html .= match ($type) {
                'text' => self::renderText($attrs),
                'quote' => self::renderQuote($attrs),
                'images' => self::renderImages($attrs, $post),
                'title' => self::renderTitle($attrs),
                'subtitle' => self::renderSubtitle($attrs),
                'video' => self::renderVideo($attrs),
                'embed' => self::renderEmbed($attrs),
                'outline' => self::renderOutline($attrs),
                'related' => self::renderRelated($attrs),
                'accordion' => self::renderAccordion($attrs, $post),
                'regions_map' => self::renderRegionsMap($attrs),
                default => '',
            };
        }

        return trim($html);
    }

    public static function renderToPlainText(Post $post): string
    {
        if (empty($post->content) || !is_array($post->content)) {
            return '';
        }

        return self::extractPlainTextFromContentBlocks($post->content);
    }

    /**
     * Shared plain-text extraction logic for flexible content blocks.
     *
     * @param array<int|string, array<string, mixed>> $content
     */
    public static function extractPlainTextFromContentBlocks(array $content): string
    {
        $text = '';

        foreach ($content as $block) {
            $attrs = $block['attributes'] ?? [];

            if (isset($attrs['text'])) {
                $text .= self::plainTextFromBlockHtml((string) $attrs['text']) . ' ';
            }
            if (isset($attrs['quote'])) {
                $text .= self::plainTextFromBlockHtml((string) $attrs['quote']) . ' ';
            }
            if (isset($attrs['title'])) {
                $text .= self::plainTextFromBlockHtml((string) $attrs['title']) . ' ';
            }
            if (isset($attrs['subtitle'])) {
                $text .= self::plainTextFromBlockHtml((string) $attrs['subtitle']) . ' ';
            }
            if (isset($attrs['outline'])) {
                $text .= trim((string) $attrs['outline']) . ' ';
            }
            if (isset($attrs['accordion_title'])) {
                $text .= trim((string) $attrs['accordion_title']) . ' ';
            }
            if (isset($attrs['items']) && is_array($attrs['items'])) {
                foreach ($attrs['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    if (!empty($item['title'])) {
                        $text .= trim((string) $item['title']) . ' ';
                    }
                    if (!empty($item['content'])) {
                        $text .= self::plainTextFromBlockHtml((string) $item['content']) . ' ';
                    }
                }
            }

            if (($block['type'] ?? '') === 'regions_map') {
                $text .= self::plainTextFromRegionsMapAttributes($attrs) . ' ';
            }
        }

        return trim($text);
    }

    /**
     * Plain text from block HTML for search/feeds: inserts a space between adjacent tags so
     * strip_tags() does not concatenate the last word of one element with the first of the next.
     */
    public static function plainTextFromBlockHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('/>\s*</u', '> <', $html) ?? $html;
        $plain = strip_tags($html);
        $plain = preg_replace('/\s+/u', ' ', trim($plain)) ?? trim($plain);

        return $plain;
    }

    private static function renderText(array $attrs): string
    {
        $text = $attrs['text'] ?? '';
        if (empty($text)) {
            return '';
        }
        return $text;
    }

    private static function renderQuote(array $attrs): string
    {
        $quote = $attrs['quote'] ?? '';
        if (empty($quote)) {
            return '';
        }
        $author = $attrs['author'] ?? '';
        $html = '<blockquote>' . $quote;
        if ($author) {
            $html .= '<cite>' . e($author) . '</cite>';
        }
        $html .= '</blockquote>';
        return $html;
    }

    private static function renderImages(array $attrs, Post $post): string
    {
        $images = $attrs['images'] ?? [];
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($images) || $images === []) {
            return '';
        }

        if (isset($images['link'])) {
            $images = [$images];
        }

        $html = '';
        foreach ($images as $image) {
            if (is_object($image)) {
                $image = (array) $image;
            }
            if (! is_array($image)) {
                continue;
            }

            $link = $image['link'] ?? null;
            $imageId = $image['id'] ?? null;
            $description = $image['description'] ?? '';

            if ($link && $imageId) {
                $url = ImageService::getImageUrl(
                    $imageId,
                    $link,
                    ImageService::TYPE_CONTENT_IMAGE,
                    ImageService::SIZE_ORIGINAL,
                    true,
                    $post->language_code
                );
            } elseif ($link) {
                $url = $link;
            } else {
                continue;
            }

            $url = self::canonicalizeMediaUrlForFeed($url, $post);
            if ($url === null || $url === '') {
                continue;
            }

            $html .= '<figure>';
            $html .= '<img src="' . e($url) . '" alt="' . e($description) . '"/>';
            if ($description) {
                $html .= '<figcaption>' . e($description) . '</figcaption>';
            }
            $html .= '</figure>';
        }

        return $html;
    }

    /**
     * Feeds and external readers need absolute URLs; remap APP_URL storage links to edition canonical host.
     */
    private static function canonicalizeMediaUrlForFeed(?string $url, Post $post): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $siteUrl = self::canonicalBaseUrl($post->language_code);
        $appUrl = rtrim((string) config('app.url'), '/');

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if ($appUrl !== '' && $appUrl !== $siteUrl && str_starts_with($url, $appUrl)) {
                return $siteUrl . substr($url, strlen($appUrl));
            }

            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $siteUrl . $url;
        }

        return $siteUrl . '/' . ltrim($url, '/');
    }

    private static function renderTitle(array $attrs): string
    {
        $title = $attrs['title'] ?? '';
        if (empty($title)) {
            return '';
        }
        return '<h2>' . e($title) . '</h2>';
    }

    private static function renderSubtitle(array $attrs): string
    {
        $subtitle = $attrs['subtitle'] ?? '';
        if (empty($subtitle)) {
            return '';
        }
        return '<h3>' . e($subtitle) . '</h3>';
    }

    private static function renderVideo(array $attrs): string
    {
        $url = $attrs['video_url'] ?? '';
        if (empty($url)) {
            return '';
        }
        $description = $attrs['video_description'] ?? '';
        return '<figure>'
            . '<iframe src="' . e($url) . '" frameborder="0" allowfullscreen></iframe>'
            . ($description ? '<figcaption>' . e($description) . '</figcaption>' : '')
            . '</figure>';
    }

    private static function renderEmbed(array $attrs): string
    {
        $code = $attrs['embed_code'] ?? '';
        if (empty($code)) {
            return '';
        }
        return '<div>' . $code . '</div>';
    }

    /**
     * Emitted by ContentInsertionCodeService::expand() from outline-heading h3 tags in text.
     */
    private static function renderOutline(array $attrs): string
    {
        $outline = $attrs['outline'] ?? '';
        if ($outline === '') {
            return '';
        }

        return '<h3 class="outline-heading">' . e($outline) . '</h3>';
    }

    /**
     * Inserted when {{ related_idKEY }} references a related block with show_insertion_code.
     *
     * @param  array<string, mixed>  $attrs
     */
    private static function renderRelated(array $attrs): string
    {
        $ids = $attrs['related_posts'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return '';
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return '';
        }

        $relatedPosts = Post::whereIn('id', $ids)
            ->where('status', Post::STATUS_PUBLISHED)
            ->get()
            ->sortBy(fn (Post $p) => array_search($p->id, $ids, true));

        if ($relatedPosts->isEmpty()) {
            return '';
        }

        $html = '<aside class="related-posts"><ul>';
        foreach ($relatedPosts as $relatedPost) {
            $url = self::getPostUrl($relatedPost);
            $html .= '<li><a href="' . e($url) . '">' . e($relatedPost->title) . '</a></li>';
        }
        $html .= '</ul></aside>';

        return $html;
    }

    /**
     * Flatten accordion items into <h2>section title</h2> + <h3>item title</h3><div>body</div> for
     * feeds (RSS/Google News/Yandex/Dzen/FB Instant) since these consumers don't render real
     * expandable accordions.
     *
     * After {@see ContentInsertionCodeService::expand()} each item's body is a structured
     * `blocks` list (text fragments + referenced renderable blocks); each sub-block is rendered
     * via the same per-type renderers used at top level. For backwards safety raw `content` HTML
     * is still accepted when an item bypassed the expansion.
     *
     * @param  array<string, mixed>  $attrs
     */
    private static function renderAccordion(array $attrs, Post $post): string
    {
        $items = $attrs['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return '';
        }

        $accordionTitle = trim((string) ($attrs['accordion_title'] ?? ''));

        $itemsHtml = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $body = self::renderAccordionItemBody($item, $post);

            if ($title === '' && trim(strip_tags($body)) === '') {
                continue;
            }

            if ($title !== '') {
                $itemsHtml .= '<h3>' . e($title) . '</h3>';
            }
            if ($body !== '') {
                $itemsHtml .= '<div>' . $body . '</div>';
            }
        }

        if ($itemsHtml === '') {
            return '';
        }

        $html = '';
        if ($accordionTitle !== '') {
            $html .= '<h2>' . e($accordionTitle) . '</h2>';
        }
        $html .= $itemsHtml;

        return $html;
    }

    /**
     * Render one accordion item's body. Prefers the expanded `blocks` list (post-expand shape);
     * falls back to raw `content` HTML for safety in code paths that bypass expansion.
     *
     * @param  array<string, mixed>  $item
     */
    private static function renderAccordionItemBody(array $item, Post $post): string
    {
        $blocks = $item['blocks'] ?? null;
        if (is_array($blocks) && $blocks !== []) {
            $html = '';
            foreach ($blocks as $sub) {
                if (! is_array($sub)) {
                    continue;
                }
                $type = $sub['type'] ?? null;
                $attrs = $sub['attributes'] ?? [];
                $html .= match ($type) {
                    'text' => self::renderText($attrs),
                    'quote' => self::renderQuote($attrs),
                    'images' => self::renderImages($attrs, $post),
                    'video' => self::renderVideo($attrs),
                    'embed' => self::renderEmbed($attrs),
                    'regions_map' => self::renderRegionsMap($attrs),
                    default => '',
                };
            }
            return $html;
        }

        return (string) ($item['content'] ?? '');
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private static function renderRegionsMap(array $attrs): string
    {
        $parts = [];

        $title = trim((string) ($attrs['title'] ?? ''));
        if ($title !== '') {
            $parts[] = '<h2>' . e($title) . '</h2>';
        }

        $comment = $attrs['comment'] ?? null;
        if (is_string($comment) && trim($comment) !== '') {
            $parts[] = '<p>' . e(trim($comment)) . '</p>';
        } elseif (is_array($comment)) {
            foreach ($comment as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $parts[] = '<p>' . e(trim($line)) . '</p>';
                }
            }
        }

        $parts[] = '<p><em>' . e(__('Regions map')) . '</em></p>';

        return implode('', $parts);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private static function plainTextFromRegionsMapAttributes(array $attrs): string
    {
        $parts = [];

        $comment = $attrs['comment'] ?? null;
        if (is_string($comment) && trim($comment) !== '') {
            $parts[] = trim($comment);
        } elseif (is_array($comment)) {
            foreach ($comment as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $parts[] = trim($line);
                }
            }
        }

        $colorLabels = $attrs['color_labels'] ?? null;
        if (is_array($colorLabels)) {
            foreach ($colorLabels as $label) {
                if (is_string($label) && trim($label) !== '') {
                    $parts[] = trim($label);
                }
            }
        }

        $regions = $attrs['regions'] ?? null;
        if (is_array($regions)) {
            foreach ($regions as $region) {
                if (!is_array($region)) {
                    continue;
                }
                if (!empty($region['comment']) && is_string($region['comment'])) {
                    $parts[] = trim($region['comment']);
                }
            }
        }

        return trim(implode(' ', $parts));
    }

    public static function getAuthorName(Post $post): string
    {
        if ($post->columnist) {
            return $post->columnist->full_name;
        }

        $authors = $post->authors;
        if ($authors->isNotEmpty()) {
            return $authors->pluck('full_name')->implode(', ');
        }

        return 'The Insider';
    }

    public static function getPostUrl(Post $post): string
    {
        return self::canonicalBaseUrl($post->language_code) . $post->getPath();
    }

    public static function getPostImageUrl(Post $post): ?string
    {
        $localUrl = $post->image_url;
        if (empty($localUrl)) {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $siteUrl = self::canonicalBaseUrl($post->language_code);

        if ($appUrl !== $siteUrl && str_starts_with($localUrl, $appUrl)) {
            return $siteUrl . substr($localUrl, strlen($appUrl));
        }

        if (str_starts_with($localUrl, '/')) {
            return $siteUrl . $localUrl;
        }

        return $localUrl;
    }

    private static function canonicalBaseUrl(string $languageCode): string
    {
        $host = $languageCode === 'ru'
            ? config('app.ru_canonical_host')
            : config('app.en_canonical_host');

        return rtrim((string) $host, '/');
    }
}
