<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Models\Author;
use App\Models\InvestigationTheme;
use App\Models\Post;
use App\Models\PostAuthor;
use App\Models\Tag;
use App\Models\Termin;
use App\Models\PostTypes\OnlineMessage;
use App\Models\User;
use App\Models\PostOwner;
use App\Models\SyncLog;
use App\Models\CollectionPost;

class ClearDatabase extends Command
{
    protected $signature = 'db:clear {--force : Force clear without confirmation}';
    protected $description = 'Clear all synced data from database';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  Это удалит ВСЕ данные из базы! Вы уверены?')) {
                $this->info('Очистка отменена.');
                return 0;
            }
        }

        $this->info('Начинаем очистку базы данных...');

        $this->clearTable(PostOwner::class, 'post_owners');
        $this->clearTable(PostAuthor::class, 'post_authors');
        $this->clearTable(CollectionPost::class, 'collection_post');
        $this->clearTable(OnlineMessage::class, 'online_messages');
        $this->clearTable(Post::class, 'posts');
        $this->clearTable(Category::class, 'categories');
        $this->clearTable(Author::class, 'authors');
        $this->clearTable(InvestigationTheme::class, 'investigation_themes');
        $this->clearTable(Termin::class, 'termins');
        $this->clearTable(Tag::class, 'tags');
        $this->clearTable(User::class, 'users');
        $this->clearTable(SyncLog::class, 'sync_logs');

        $this->info('✅ База данных успешно очищена!');

        return 0;
    }

    private function clearTable($model, $tableName)
    {
        try {
            $count = $model::count();
            $model::truncate();
            $this->line("  ✓ Очищено {$tableName}: {$count} записей");
        } catch (\Exception $e) {
            $this->error("  ✗ Ошибка при очистке {$tableName}: " . $e->getMessage());
        }
    }
}

