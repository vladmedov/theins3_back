<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PromoteStrongParagraphsToH3 extends Command
{
    protected $signature = 'content:promote-strong-paragraphs-to-h3
                            {--dry-run : Показать изменения без сохранения}
                            {--post= : ID поста (обработать один пост)}';

    protected $description = 'В блоках text заменяет <p> на <h3>, если внутри параграфа ровно один <strong> (и больше ничего); удаляет инлайн-стили у таких параграфов';

    public function handle(): int
    {
        try {
            $this->getOutput()->writeln('content:promote-strong-paragraphs-to-h3 — запуск.');

            $dryRun = $this->option('dry-run');
            $postId = $this->option('post');

            $query = Post::query();
            if ($postId !== null && $postId !== '') {
                $query->where('id', (int) $postId);
            }

            $totalReplaced = 0;
            $postsModified = 0;
            $chunkSize = 50;

            $this->getOutput()->writeln('Загрузка постов чанками по ' . $chunkSize . '...');

            $query->chunkById($chunkSize, function ($posts) use ($dryRun, &$totalReplaced, &$postsModified) {
                foreach ($posts as $post) {
                    $content = $post->content;
                    if (!is_array($content)) {
                        continue;
                    }

                    $modified = false;
                    foreach ($content as $key => $block) {
                        $type = $block['type'] ?? null;
                        $attrs = $block['attributes'] ?? [];
                        if ($type !== 'text' || !isset($attrs['text'])) {
                            continue;
                        }

                        $text = $attrs['text'];
                        [$newText, $replaced] = $this->transformText($text);
                        if ($replaced > 0) {
                            $content[$key]['attributes']['text'] = $newText;
                            $modified = true;
                            $totalReplaced += $replaced;
                        }
                    }

                    if ($modified) {
                        $postsModified++;
                        if (!$dryRun) {
                            $this->assignContentAndSave($post, $content);
                        }
                        $this->getOutput()->writeln('Post #' . $post->id . ': обновлён.');
                    }
                }
            });

            if ($dryRun) {
                $this->getOutput()->writeln('<comment>Режим dry-run: изменения не сохранены.</comment>');
            }
            $this->getOutput()->writeln("<info>Готово. Постов изменено: {$postsModified}, всего замен параграфов: {$totalReplaced}.</info>");

            return 0;
        } catch (\Throwable $e) {
            $this->getOutput()->writeln('<error>Ошибка: ' . $e->getMessage() . '</error>');
            $this->getOutput()->writeln($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Заменяет параграфы вида <p><strong>В сегодняшней сводке:</strong></p> на <h3>...</h3>.
     * Меняем только если внутри <p> ровно один <strong> и внутри <strong> только текст (без тегов ul, li и т.д.).
     *
     * @return array{0: string, 1: int} [новый HTML, количество выполненных замен]
     */
    private function transformText(string $html): array
    {
        $count = 0;
        $pattern = '/<p(\s[^>]*)?>\s*<strong(\s[^>]*)?>(.*?)<\/strong>\s*<\/p>/is';

        $newHtml = preg_replace_callback($pattern, function (array $m) use (&$count): string {
            $inner = $m[3];
            // Меняем только если внутри <strong> нет других тегов (чистый заголовок)
            if (str_contains($inner, '<')) {
                return $m[0];
            }
            $count++;
            $inner = $this->stripInlineStylesFromTagContent($inner);
            return '<h3>' . $inner . '</h3>';
        }, $html);

        return [$newHtml, $count];
    }

    /**
     * Удаляет атрибут style из тегов в переданном фрагменте.
     */
    private function stripInlineStylesFromTagContent(string $fragment): string
    {
        return preg_replace('/\s+style\s*=\s*["\'][^"\']*["\']/i', '', $fragment);
    }

    /**
     * Формат для сохранения через CompactFlexibleCast: массив блоков с key, layout, attributes.
     */
    private function assignContentAndSave(Post $post, array $content): void
    {
        $payload = [];
        foreach ($content as $key => $block) {
            $payload[] = [
                'key' => $key,
                'layout' => $block['type'],
                'attributes' => $block['attributes'],
            ];
        }
        $post->content = $payload;
        $post->save();
    }
}
