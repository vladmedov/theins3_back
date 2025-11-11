<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Post;

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
            
            $this->info('Найдено ключей: ' . count($keys));
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
                        // Обновляем счетчик в БД
                        $post = Post::find($postId);
                        
                        if ($post) {
                            // Используем прямое обновление БД без timestamps
                            Post::where('id', $postId)->increment('views_count', $count);
                            $this->info("Post ID {$postId}: +{$count} просмотров");
                            $processed++;
                        } else {
                            $this->warn("Post ID {$postId} не найден");
                            $errors++;
                        }
                        
                        // Удаляем обработанный ключ из Redis
                        $redis->rawCommand('DEL', $key);
                    }
                }
                
                DB::commit();
                
                $this->info("Обработка завершена. Обработано: {$processed}, Ошибок: {$errors}");
                
                // Логируем результат
                Log::info('ProcessViewCounts completed', [
                    'processed' => $processed,
                    'errors' => $errors,
                    'total_keys' => count($keys)
                ]);
                
                return 0;
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $this->error('Ошибка при обработке: ' . $e->getMessage());
            Log::error('ProcessViewCounts failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}

