<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostViewMilestoneNovaNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessViewCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:view-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обработка счетчиков просмотров из Redis и обновление БД';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начало обработки счетчиков просмотров...');

        try {
            // Получаем все ключи счетчиков просмотров
            $redis = Redis::connection()->client();
            $keys = $redis->rawCommand('KEYS', 'view_count:*');

            if (empty($keys)) {
                $this->info('Нет счетчиков для обработки');

                return 0;
            }

            $this->info('Найдено ключей: '.count($keys));
            $processed = 0;
            $errors = 0;

            // Обрабатываем каждый ключ
            DB::beginTransaction();

            try {
                foreach ($keys as $key) {
                    // Извлекаем post_id из ключа (формат: view_count:123)
                    $postId = (int) str_replace('view_count:', '', $key);

                    // Получаем значение счетчика
                    $count = (int) $redis->rawCommand('GET', $key);

                    if ($count > 0) {
                        $post = Post::query()
                            ->select(['id', 'views_count', 'type', 'title', 'language_code'])
                            ->with('owners')
                            ->find($postId);

                        if (! $post) {
                            $this->warn("Post ID {$postId} не найден");
                            $errors++;
                            $redis->rawCommand('DEL', $key);

                            continue;
                        }

                        $viewsBefore = (int) $post->views_count;

                        // Обновляем views_count напрямую в таблице, не трогая updated_at.
                        $affected = DB::table((new Post)->getTable())
                            ->where('id', $postId)
                            ->increment('views_count', $count);

                        if ($affected > 0) {
                            $this->info("Post ID {$postId}: +{$count} просмотров");
                            $processed++;
                            app(PostViewMilestoneNovaNotifier::class)
                                ->notifyForCountIncrease($post, $viewsBefore, $viewsBefore + $count);
                            $redis->rawCommand('DEL', $key);
                        } else {
                            $this->warn("Post ID {$postId}: views_count не обновлён (0 строк), ключ Redis не удалён");
                            $errors++;
                        }
                    }
                }

                DB::commit();

                $this->info("Обработка завершена. Обработано: {$processed}, Ошибок: {$errors}");

                // Логируем результат
                Log::info('ProcessViewCounts completed', [
                    'processed' => $processed,
                    'errors' => $errors,
                    'total_keys' => count($keys),
                ]);

                return 0;

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->error('Ошибка при обработке: '.$e->getMessage());
            Log::error('ProcessViewCounts failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
