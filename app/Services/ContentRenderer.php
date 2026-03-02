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
                default => '',
            };
        }

        return trim($html);
    }

    public static function renderToPlainText(Post $post): string
    {
        $content = $post->content;

        if (empty($content) || !is_array($content)) {
            return '';
        }

        $text = '';

        foreach ($content as $block) {
            $attrs = $block['attributes'] ?? [];

            if (isset($attrs['text'])) {
                $text .= strip_tags($attrs['text']) . ' ';
            }
            if (isset($attrs['quote'])) {
                $text .= strip_tags($attrs['quote']) . ' ';
            }
            if (isset($attrs['title'])) {
                $text .= strip_tags($attrs['title']) . ' ';
            }
            if (isset($attrs['subtitle'])) {
                $text .= strip_tags($attrs['subtitle']) . ' ';
            }
        }

        return trim($text);
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
        if (empty($images)) {
            return '';
        }

        if (isset($images['link'])) {
            $images = [$images];
        }

        $html = '';
        foreach ($images as $image) {
            $link = $image['link'] ?? null;
            $imageId = $image['id'] ?? null;
            $description = $image['description'] ?? '';

            if ($link && $imageId) {
                $url = ImageService::getImageUrl(
                    $imageId,
                    $link,
                    ImageService::TYPE_CONTENT_IMAGE,
                    ImageService::SIZE_ORIGINAL,
                    false
                );
            } elseif ($link) {
                $url = $link;
            } else {
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
        return rtrim(config('app.site_url'), '/') . $post->getPath();
    }

    public static function getPostImageUrl(Post $post): ?string
    {
        $localUrl = $post->image_url;
        if (empty($localUrl)) {
            return null;
        }

        $appUrl = rtrim(config('app.url'), '/');
        $siteUrl = rtrim(config('app.site_url'), '/');

        if ($appUrl !== $siteUrl && str_starts_with($localUrl, $appUrl)) {
            return $siteUrl . substr($localUrl, strlen($appUrl));
        }

        return $localUrl;
    }
}
