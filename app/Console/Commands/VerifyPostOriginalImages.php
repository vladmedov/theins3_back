<?php

namespace App\Console\Commands;

use App\Enums\PostTypes;
use App\Models\Post;
use App\Services\ImageService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class VerifyPostOriginalImages extends Command
{
    protected $signature = 'posts:verify-original-images
                            {--chunk=200 : Сколько постов за один chunk}
                            {--inline-html : Проверять URL /storage/... в HTML (текст онлайна, text, quote и т.д.)}';

    protected $description = 'Проверяет наличие original-файлов изображений: обложка, галереи в контенте, картинки в онлайн-сообщениях';

    public function handle(): int
    {
        /** @var Filesystem $disk */
        $disk = Storage::disk('public');
        $chunk = max(1, (int) $this->option('chunk'));
        $scanHtml = (bool) $this->option('inline-html');

        $query = Post::query()
            ->without(['category', 'authors', 'columnist'])
            ->with('onlineMessages');

        $total = (clone $query)->count();
        $this->info("Постов к проверке: {$total}");

        $missingLines = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById($chunk, function ($posts) use ($disk, $scanHtml, $bar, &$missingLines) {
            foreach ($posts as $post) {
                $missing = $this->collectMissingForPost($post, $disk, $scanHtml);
                if ($missing !== []) {
                    $bar->clear();
                    foreach ($missing as $line) {
                        $this->line($line);
                        $missingLines++;
                    }
                    $bar->display();
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Готово. Строк с отсутствующими original: {$missingLines}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectMissingForPost(Post $post, Filesystem $disk, bool $scanHtml): array
    {
        $out = [];

        if (!empty($post->image) && !$disk->exists($post->image)) {
            $out[] = (string) $post->id;
        }

        if ($post->type === PostTypes::ONLINE) {
            foreach ($post->onlineMessages as $message) {
                foreach ($this->normalizeImagesArray($message->images ?? []) as $img) {
                    $path = $this->originalPathFromGallery($img, ImageService::TYPE_ONLINE_IMAGE);
                    if ($path !== null && !$disk->exists($path)) {
                        $out[] = (string) $post->id;
                    }
                }
                if ($scanHtml) {
                    foreach ($this->storageImagePathsFromHtml((string) ($message->text ?? '')) as $rel) {
                        if (!$disk->exists($rel)) {
                            $out[] = (string) $post->id;
                        }
                    }
                }
            }

            return array_values(array_unique($out));
        }

        $raw = $post->getRawOriginal('content');
        $blocks = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($blocks)) {
            return array_values(array_unique($out));
        }

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $attrs = $block['attributes'] ?? [];
            if (!is_array($attrs)) {
                continue;
            }

            if ($type === 'images') {
                foreach ($this->normalizeImagesArray($attrs['images'] ?? []) as $img) {
                    $path = $this->originalPathFromGallery($img, ImageService::TYPE_CONTENT_IMAGE);
                    if ($path !== null && !$disk->exists($path)) {
                        $out[] = (string) $post->id;
                    }
                }
            }

            if ($scanHtml && is_string($type) && in_array($type, ['text', 'quote', 'title', 'subtitle', 'embed'], true)) {
                foreach ($this->htmlFieldsForBlock($type, $attrs) as $html) {
                    foreach ($this->storageImagePathsFromHtml($html) as $rel) {
                        if (!$disk->exists($rel)) {
                            $out[] = (string) $post->id;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeImagesArray(mixed $images): array
    {
        if (!is_array($images) || $images === []) {
            return [];
        }
        if (isset($images['link'])) {
            return [$images];
        }

        return $images;
    }

    /**
     * @param  array<string, mixed>  $img
     */
    private function originalPathFromGallery(array $img, string $imageType): ?string
    {
        $link = $img['link'] ?? null;
        $imageId = $img['id'] ?? null;
        if ($link === null || $link === '' || $imageId === null || $imageId === '') {
            return null;
        }

        return ImageService::getImagePath($imageId, $imageType, ImageService::SIZE_ORIGINAL)
            . '/' . basename((string) $link);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private function htmlFieldsForBlock(string $type, array $attrs): array
    {
        return match ($type) {
            'text' => [(string) ($attrs['text'] ?? '')],
            'quote' => [(string) ($attrs['quote'] ?? '')],
            'title' => [(string) ($attrs['title'] ?? '')],
            'subtitle' => [(string) ($attrs['subtitle'] ?? '')],
            'embed' => [(string) ($attrs['embed_code'] ?? '')],
            default => [],
        };
    }

    /**
     * Относительные пути на диске public (без префикса storage/).
     *
     * @return list<string>
     */
    private function storageImagePathsFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }
        if (!preg_match_all('#(?:https?://[^/\\s"\']+)?/storage/([^"\'\\s>]+)#i', $html, $m)) {
            return [];
        }
        $paths = [];
        foreach ($m[1] as $raw) {
            $decoded = html_entity_decode(rawurldecode(str_replace(' ', '%20', $raw)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = strtok($decoded, '?#') ?: '';
            if ($decoded === '' || str_contains($decoded, '..')) {
                continue;
            }
            if (!preg_match('#^(content_image|post_cover|online_image)/#', $decoded)) {
                continue;
            }
            $paths[] = $decoded;
        }

        return array_values(array_unique($paths));
    }
}
