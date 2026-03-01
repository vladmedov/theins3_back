<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SyncLog;
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
use App\Models\CollectionPost;

class SyncFromLegacyDb extends Command
{
    protected $signature = 'sync:legacy {--reset-stuck : Reset stuck sync processes}';
    protected $description = 'Incrementally sync data from legacy database';

    private $legacy_db;
    private const LOCK_TIMEOUT = 180; // 3 minutes (short cache lock, real check is via sync_logs)
    private const ACTIVITY_CHECK_MINUTES = 1; // Consider process dead if no updates for 1 minute

    public function __construct()
    {
        parent::__construct();
        $this->legacy_db = DB::connection('legacy_pgsql');
    }

    public function handle()
    {
        // Опция для сброса зависших процессов
        if ($this->option('reset-stuck')) {
            SyncLog::resetStuckProcesses(self::ACTIVITY_CHECK_MINUTES);
            
            // Принудительно освобождаем блокировку в кэше
            $lock = Cache::lock('legacy-sync-lock', self::LOCK_TIMEOUT);
            $lock->forceRelease();
            
            $this->info('Stuck processes and locks have been reset');
            return 0;
        }

        // Проверяем активные процессы по таблице sync_logs
        $runningProcesses = SyncLog::where('status', 'running')
            ->where('updated_at', '>', now()->subMinutes(self::ACTIVITY_CHECK_MINUTES))
            ->count();

        if ($runningProcesses > 0) {
            $this->warn('Another sync process is already running (detected by sync_logs activity)');
            Log::warning('Legacy sync skipped: another active process detected');
            return 1;
        }

        // Получаем блокировку для предотвращения параллельного выполнения
        $lock = Cache::lock('legacy-sync-lock', self::LOCK_TIMEOUT);

        if (!$lock->get()) {
            $this->warn('Another sync process is already running (cache lock)');
            Log::warning('Legacy sync skipped: cache lock is held');
            return 1;
        }

        try {
            $this->info('Starting incremental sync from legacy database...');
            Log::info('Legacy sync started');

            $this->syncAdmins();

            // Синхронизация для русского региона (region_id = 1)
            $this->syncCategories(1);
            $this->syncAuthors(1);
            $this->syncInvestigationThemes(1);
            $this->syncPosts(1);

            // Синхронизация для английского региона (region_id = 3)
            $this->syncCategories(3);
            $this->syncAuthors(3);
            $this->syncInvestigationThemes(3);
            $this->syncPosts(3);

            // Синхронизация связей
            $this->syncPostAuthors(1);
            $this->syncPostAuthors(3);
            $this->syncThemePosts();
            $this->syncAdminRelations();
            $this->syncCollections();

            $this->info('Sync completed successfully!');
            Log::info('Legacy sync completed successfully');

            return 0;
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('Legacy sync failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $lock->release();
        }
    }

    private function syncCategories(int $regionId): void
    {
        $entityType = "categories_region_{$regionId}";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $languageCode = $regionId === 1 ? 'ru' : 'en';
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing categories for region {$regionId} (updated after {$lastSyncTime})");

            // Получаем ID категорий для региона
            $categoryIds = $this->legacy_db->select('
                SELECT regionable_id FROM public.region_relations
                WHERE region_id = ? AND regionable_type = \'Rubric\'
                ORDER BY id ASC 
            ', [$regionId]);

            if (empty($categoryIds)) {
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $categoryIds = array_column($categoryIds, 'regionable_id');

            // Получаем только обновленные категории
            $categories = $this->legacy_db->select('
                SELECT * FROM public.rubrics
                WHERE id IN (' . implode(',', $categoryIds) . ')
                AND updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            foreach ($categories as $category) {
                Category::updateOrCreate(
                    ['id' => $category->id],
                    [
                        'language_code' => $languageCode,
                        'slug' => $category->slug,
                        'type' => match ($category->special) {
                            'news' => 'news',
                            'opinion' => 'opinion',
                            'simple' => 'default',
                            'longread' => 'default',
                            'confession' => 'default',
                        },
                        'title' => $category->title,
                        'position' => $category->position ?? 0,
                        'is_show_in_menu' => $category->show_in_menu,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                    ]
                );
                $this->line("  → Category ID: {$category->id} - {$category->title}");
                $syncedCount++;
            }

            $this->info("Synced {$syncedCount} categories for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncAuthors(int $regionId): void
    {
        $entityType = "authors_region_{$regionId}";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $languageCode = $regionId === 1 ? 'ru' : 'en';
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing authors for region {$regionId} (updated after {$lastSyncTime})");

            $authorIds = $this->legacy_db->select('
                SELECT regionable_id FROM public.region_relations
                WHERE region_id = ? AND regionable_type = \'Person\'
                ORDER BY id ASC 
            ', [$regionId]);

            if (empty($authorIds)) {
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $authorIds = array_column($authorIds, 'regionable_id');

            $authors = $this->legacy_db->select('
                SELECT * FROM public.people
                WHERE id IN (' . implode(',', $authorIds) . ')
                AND updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            foreach ($authors as $author) {
                // Обрабатываем visible_in_post из старой БД
                $isVisibleInPost = $author->visible_in_post ?? true;
                
                Author::updateOrCreate(
                    ['id' => $author->id],
                    [
                        'language_code' => $languageCode,
                        'slug' => $author->slug,
                        'first_name' => $author->first_name,
                        'last_name' => $author->last_name,
                        'avatar' => $author->image,
                        'position' => $author->work_position,
                        'description' => $author->description,
                        'twitter' => $author->twitter,
                        'facebook' => $author->facebook,
                        'allowed_post_types' => ['article', 'opinion', 'news', 'online'],
                        'post_types_with_hidden_author_name' => $isVisibleInPost === false ? ['news'] : [],
                        'is_author_page_hidden' => $isVisibleInPost === false ? true : false,
                        'is_columnist_page_hidden' => false,
                    ]
                );
                $this->line("  → Author ID: {$author->id} - {$author->first_name} {$author->last_name}" . 
                    ($isVisibleInPost === false ? ' [hidden in news]' : ''));
                $syncedCount++;
            }

            $this->info("Synced {$syncedCount} authors for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncInvestigationThemes(int $regionId): void
    {
        $entityType = "investigation_themes_region_{$regionId}";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $languageCode = $regionId === 1 ? 'ru' : 'en';
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing investigation themes for region {$regionId} (updated after {$lastSyncTime})");

            $themeIds = $this->legacy_db->select('
                SELECT regionable_id FROM public.region_relations
                WHERE region_id = ? AND regionable_type = \'Theme\'
                ORDER BY id ASC 
            ', [$regionId]);

            if (empty($themeIds)) {
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $themeIds = array_column($themeIds, 'regionable_id');

            $themes = $this->legacy_db->select('
                SELECT * FROM public.themes
                WHERE id IN (' . implode(',', $themeIds) . ')
                AND updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            $position = count($themes) * 10;
            
            foreach ($themes as $theme) {
                InvestigationTheme::updateOrCreate(
                    ['id' => $theme->id],
                    [
                        'language_code' => $languageCode,
                        'slug' => $theme->slug,
                        'title' => $theme->title,
                        'description' => $theme->description,
                        'position' => !empty($theme->position) ? $theme->position : $position,
                        'cover_image' => 'https://theins.ru/storage/theme/' . $theme->id . '/' . $theme->image,
                        'is_main' => $theme->slug === 'otraviteli-iz-fsb' && $languageCode === 'ru',
                        'created_at' => $theme->created_at,
                        'updated_at' => $theme->updated_at,
                    ]
                );
                $this->line("  → Theme ID: {$theme->id} - {$theme->title}");
                $position--;
                $syncedCount++;
            }

            $this->info("Synced {$syncedCount} investigation themes for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncPosts(int $regionId): void
    {
        $entityType = "posts_region_{$regionId}";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $languageCode = $regionId === 1 ? 'ru' : 'en';
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing posts for region {$regionId} (updated after {$lastSyncTime})");

            $postIds = $this->legacy_db->select('
                SELECT regionable_id FROM public.region_relations
                WHERE region_id = ? AND regionable_type = \'Post\'
                ORDER BY id DESC 
            ', [$regionId]);

            if (empty($postIds)) {
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $postIds = array_column($postIds, 'regionable_id');

            // Получаем только обновленные посты
            $posts = $this->legacy_db->select('
                SELECT posts.*, rubric_relations.rubric_id FROM public.posts
                LEFT JOIN (
                    SELECT rubricable_id, MIN(rubric_id) as rubric_id
                    FROM public.rubric_relations 
                    WHERE rubricable_type = \'Post\'
                    GROUP BY rubricable_id
                ) as rubric_relations ON rubric_relations.rubricable_id = posts.id
                WHERE posts.id IN (' . implode(',', $postIds) . ')
                AND posts.updated_at > ?
                ORDER BY posts.id DESC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            foreach ($posts as $post) {
                $syncedPost = Post::updateOrCreate(
                    ['id' => $post->id],
                    [
                        'language_code' => $languageCode,
                        'slug' => $post->slug ?? $post->id,
                        'type' => match ($post->type) {
                            'Post::News' => 'news',
                            'Post::Opinion' => 'opinion',
                            'Post::Article' => 'article',
                            'Post::Online' => 'online',
                            'Post::Card' => 'article',
                        },
                        'category_id' => $post->rubric_id,
                        'title' => $post->title,
                        'status' => $post->published ? 'published' : 'draft',
                        'author_visibility' => match ($post->type) {
                            'Post::News' => 'force_hidden',
                            default => 'default'
                        },
                        'image' => $post->preview_image ?? $post->detail_image ?? null,
                        'image_description' => $post->image_description,
                        'published_at' => $post->published_at,
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at,
                        'lead' => $post->lead,
                        'is_super_news' => $post->super_news,
                        'views_count' => $post->viewed,
                    ]
                );
                
                $this->line("  → Post ID: {$post->id} - {$post->title}");
                
                // Синхронизация контента и тегов для каждого поста
                if ($post->type === 'Post::Online') {
                    $this->syncOnlineMessages($post->id, $languageCode);
                } else {
                    $this->syncPostContent($post->id, $languageCode);
                }
                $this->syncPostTags($post->id, $languageCode);
                
                $syncedCount++;
            }

            $this->info("Synced {$syncedCount} posts for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncPostAuthors(int $regionId): void
    {
        $entityType = "post_authors_region_{$regionId}";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $languageCode = $regionId === 1 ? 'ru' : 'en';
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing post authors for region {$regionId} (updated after {$lastSyncTime})");

            // Получаем ID авторов для региона
            $authorIds = $this->legacy_db->select('
                SELECT regionable_id FROM public.region_relations
                WHERE region_id = ? AND regionable_type = \'Person\'
                ORDER BY id ASC 
            ', [$regionId]);

            if (empty($authorIds)) {
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $authorIds = array_column($authorIds, 'regionable_id');

            // Получаем ID постов для региона
            $postIds = $this->legacy_db->select('
                SELECT regionable_id FROM public.region_relations
                WHERE region_id = ? AND regionable_type = \'Post\'
                ORDER BY id ASC 
            ', [$regionId]);

            if (empty($postIds)) {
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $postIds = array_column($postIds, 'regionable_id');

            // Получаем связи между авторами и постами
            $postAuthors = $this->legacy_db->select('
                SELECT * FROM public.person_relations
                WHERE ((personable2_type = \'Post\' AND person_id IN (' . implode(',', $authorIds) . '))
                OR (personable3_type = \'Post\' AND person_id IN (' . implode(',', $authorIds) . ')))
                AND updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            // Группируем связи по post_id, фильтруя только посты нужного региона
            $relations = [];
            foreach ($postAuthors as $postAuthor) {
                if ($postAuthor->personable2_type === 'Post' && $postAuthor->personable2_id && in_array($postAuthor->personable2_id, $postIds)) {
                    $relations[$postAuthor->personable2_id][] = $postAuthor->person_id;
                } elseif ($postAuthor->personable3_type === 'Post' && $postAuthor->personable3_id && in_array($postAuthor->personable3_id, $postIds)) {
                    $relations[$postAuthor->personable3_id][] = $postAuthor->person_id;
                }
            }

            if (empty($relations)) {
                $this->info("Synced 0 post-author relations for region {$regionId}");
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            $syncedCount = 0;
            $postIdsToSync = array_keys($relations);
            
            // Обрабатываем батчами по 1000 постов
            foreach (array_chunk($postIdsToSync, 1000) as $batch) {
                $posts = Post::where('language_code', $languageCode)
                            ->whereIn('id', $batch)
                            ->get();

                foreach ($posts as $post) {
                    if (!isset($relations[$post->id])) {
                        continue;
                    }
                    
                    foreach ($relations[$post->id] as $authorId) {
                        // Проверяем существование автора
                        if (!Author::find($authorId)) {
                            continue;
                        }
                        
                        // Для постов типа opinion автор записывается в columnist_id
                        if ($post->type === 'opinion') {
                            $post->columnist_id = $authorId;
                            $post->save();
                        } else {
                            // Для остальных типов постов - в таблицу post_authors
                    PostAuthor::updateOrCreate(
                        [
                                    'post_id' => $post->id,
                                    'author_id' => $authorId,
                        ]
                    );
                        }
                    $syncedCount++;
                    }
                }
            }

            $this->info("Synced {$syncedCount} post-author relations for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncPostTags(int $postId, string $languageCode): void
    {
        $currentPost = Post::find($postId);

        if (!$currentPost) {
            return;
        }

        $dbTagRelations = $this->legacy_db->select('
            SELECT tag_id FROM public.tag_relations
            WHERE tagable_id = ? AND tagable_type = \'Post\'
        ', [$postId]);

        $tagIds = array_column($dbTagRelations, 'tag_id');

        if (count($tagIds) === 0) {
            return;
        }

        $dbTags = $this->legacy_db->select('
            SELECT * FROM public.tags
            WHERE id IN (' . implode(',', $tagIds) . ')
        ');

        foreach ($dbTags as $dbTag) {
            $tag = Tag::where('slug', $dbTag->slug)->first();
            if (!$tag) {
                $tag = Tag::create([
                    'language_code' => $languageCode,
                    'id' => $dbTag->id,
                    'title' => $dbTag->title,
                    'slug' => $dbTag->slug,
                ]);
            }
            $currentPost->tags()->syncWithoutDetaching($tag->id);
        }
    }

    private function syncPostContent(int $postId, string $languageCode): void
    {
        $currentPost = Post::find($postId);

        if (!$currentPost) {
            return;
        }

        // Загружаем термины
        $termins = [];
        $dbTermins = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ?
            AND kind = \'term\'
        ', [$postId]);
        
        foreach ($dbTermins as $dbTermin) {
            $content = json_decode($dbTermin->content ?? '{}')->text ?? '';

            if (!isset($dbTermin->human_id) || $dbTermin->human_id === '' || $content === '') {
                continue;
            }

            $termins[$dbTermin->human_id] = $content;
        }

        // Загружаем шаблоны (изображения, видео, цитаты)
        $templates = [];
        $dbTemplates = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ?
            AND kind IN (\'image\', \'big_image\', \'gallery\', \'video\', \'quote\')
            ORDER BY position ASC
        ', [$postId]);
        
        foreach ($dbTemplates as $dbTemplate) {
            if (in_array($dbTemplate->kind, ['image', 'big_image', 'gallery'])) {
                $images = $this->legacy_db->select('
                    SELECT * FROM public.content_block_images
                    WHERE content_block_id = ?
                    ORDER BY position ASC
                ', [$dbTemplate->id]);
                
                if (count($images) === 0) {
                    continue;
                }
                
                $templates[$dbTemplate->human_id]['type'] = 'images';
                foreach ($images as $image) {
                    $templates[$dbTemplate->human_id]['attributes']['images'][] = [
                        'link' => 'https://theins.ru/storage/content_block/image/' . $image->id . '/' . $image->image,
                        'author' => $image->credit ?? '',
                        'description' => $image->caption ?? '',
                    ];
                }
            }
            
            if ($dbTemplate->kind === 'video') {
                $dbTemplateContent = json_decode($dbTemplate->content);
                if (isset($dbTemplateContent->video_embed) && $dbTemplateContent->video_embed === '') {
                    continue;
                }
                $templates[$dbTemplate->human_id]['type'] = 'video';
                $templates[$dbTemplate->human_id]['attributes'] = [
                    'video_url' => $dbTemplateContent->video_embed ?? '',
                    'video_description' => $dbTemplate->caption ?? '',
                    'video_author' => $dbTemplate->credit ?? '',
                ];
            }
            
            if ($dbTemplate->kind === 'quote') {
                $dbTemplateContent = json_decode($dbTemplate->content);
                if (isset($dbTemplateContent->quote) && $dbTemplateContent->quote === '') {
                    continue;
                }
                $templates[$dbTemplate->human_id]['type'] = 'quote';
                $templates[$dbTemplate->human_id]['attributes'] = [
                    'quote' => $dbTemplateContent->quote ?? '',
                    'quote_author' => '',
                ];
            }
        }

        // Загружаем основные блоки контента
        $content = [];
        $blocks = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ?
            AND kind IN (\'number\', \'text\', \'social\', \'related_posts\', \'audio\', \'iframe\')
            ORDER BY position ASC
        ', [$postId]);

        foreach ($blocks as $block) {
            if ($block->kind === 'number') {
                if (!isset($block->title) || $block->title === '') {
                    continue;
                }
                
                $content[] = [
                    'type' => 'outline',
                    'attributes' => [
                        'outline' => $this->cleanOutline($block->title),
                    ],
                ];
            }

            if ($block->kind === 'text') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->text) || $blockContent->text === '') {
                    continue;
                }
                $text = $blockContent->text;

                // Заменяем все ссылки на термины на код
                $text = preg_replace_callback('/<a\s+href="\{\{term_([^}]+)\}\}"[^>]*>(.*?)<\/a\s*>/is', function($matches) use ($termins, $currentPost) {
                    $terminId = $matches[1];
                    $terminTermin = $matches[2];
                    $terminCode = "{{term_" . $terminId . "}}";
                    $terminDescription = $termins[$terminCode] ?? '';
                    
                    $termin = Termin::where('termin', $terminTermin)->first();
                    if (!$termin) {
                        $termin = Termin::create([
                            'language_code' => $currentPost->language_code,
                            'termin' => $terminTermin,
                            'description' => $terminDescription,
                        ]);
                    }
                    $currentPost->termins()->syncWithoutDetaching($termin->id);
                    if ($termin) {
                        return '<code>' . $termin->termin . '</code>';
                    }
                }, $text);
                
                // Находим все вхождения h3 и шаблонов и обрабатываем их по порядку
                $matches = [];

                // Поиск всех тегов h3
                preg_match_all('/<h3>(.*?)<\/h3>/i', $text, $h3Matches, PREG_OFFSET_CAPTURE);
                if (!empty($h3Matches[0])) {
                    foreach ($h3Matches[0] as $index => $match) {
                        $matches[] = [
                            'type' => 'h3',
                            'content' => $h3Matches[1][$index][0],
                            'fullMatch' => $match[0],
                            'offset' => $match[1],
                            'length' => strlen($match[0])
                        ];
                    }
                }

                // Поиск всех шаблонов
                preg_match_all('/\{\{([^}]+)\}\}/i', $text, $templateMatches, PREG_OFFSET_CAPTURE);
                if (!empty($templateMatches[0])) {
                    foreach ($templateMatches[0] as $index => $match) {
                        $matches[] = [
                            'type' => 'template',
                            'fullMatch' => $match[0],
                            'offset' => $match[1],
                            'length' => strlen($match[0])
                        ];
                    }
                }

                // Сортируем все найденные совпадения по их позиции в тексте
                usort($matches, function($a, $b) {
                    return $a['offset'] - $b['offset'];
                });

                if (empty($matches)) {
                    // Если не найдено ни заголовков h3, ни шаблонов
                    $content[] = [
                        'type' => 'text',
                        'attributes' => [
                            'text' => $this->normalizeHtml($text),
                        ],
                    ];
                } else {
                    $currentPosition = 0;
                    
                    // Обрабатываем каждое совпадение по порядку
                    foreach ($matches as $match) {
                        // Добавляем текст до текущего совпадения
                        $textBefore = substr($text, $currentPosition, $match['offset'] - $currentPosition);
                        if (!empty(trim($textBefore))) {
                            $content[] = [
                                'type' => 'text',
                                'attributes' => [
                                    'text' => $this->normalizeHtml($textBefore),
                                ],
                            ];
                        }
                        
                        // Обрабатываем текущее совпадение в зависимости от его типа
                        if ($match['type'] === 'h3') {
                            $content[] = [
                                'type' => 'outline',
                                'attributes' => [
                                    'outline' => $this->cleanOutline($match['content']),
                                ],
                            ];
                        } else { // template
                            $templateKey = $match['fullMatch'];
                            if (isset($templates[$templateKey])) {
                                $content[] = $templates[$templateKey];
                            }
                        }
                        
                        // Обновляем текущую позицию
                        $currentPosition = $match['offset'] + $match['length'];
                    }
                    
                    // Добавляем оставшийся текст после последнего совпадения
                    $textAfter = substr($text, $currentPosition);
                    if (!empty(trim($textAfter))) {
                        $content[] = [
                            'type' => 'text',
                            'attributes' => [
                                'text' => $this->normalizeHtml($textAfter),
                            ],
                        ];
                    }
                }
            }
            
            // Обработка социальных вставок
            $socialTypes = [
                'iframe',
                'telegram',
                'twitter',
                'facebook',
                'instagram',
                'vk',
                'audio'
            ];
            if ($block->kind === 'social') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->social_embed) || $blockContent->social_embed === '') {
                    continue;
                }
                
                $embedCode = $blockContent->social_embed;
                
                $socialType = 'iframe';
                if (str_contains($embedCode, 'twitter.com') || str_contains($embedCode, 'x.com')) {
                    $socialType = 'twitter';
                } elseif (str_contains($embedCode, 't.me')) {
                    $socialType = 'telegram';
                } elseif (str_contains($embedCode, 'facebook.com')) {
                    $socialType = 'facebook';
                } elseif (str_contains($embedCode, 'instagram.com')) {
                    $socialType = 'instagram';
                } elseif (str_contains($embedCode, 'vk.com')) {
                    $socialType = 'vk';
                } elseif (isset($blockContent->social_type) && in_array($blockContent->social_type, $socialTypes)) {
                    // Fallback на тип из БД
                    $socialType = $blockContent->social_type;
                }
                
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $embedCode,
                        'embed_type' => $socialType,
                    ],
                ];
            }
            if ($block->kind === 'audio') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->audio_embed) || $blockContent->audio_embed === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $blockContent->audio_embed,
                        'embed_type' => 'audio',
                    ],
                ];
            }
            if ($block->kind === 'iframe') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->iframe) || $blockContent->iframe === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $blockContent->iframe,
                        'embed_type' => 'iframe',
                    ],
                ];
            }
            if ($block->kind === 'related_posts') {
                $blockContent = json_decode($block->content);
                $title = $blockContent->related_posts_title ?? '';

                $relatedPosts = $this->legacy_db->select('
                    SELECT * FROM public.post_relations
                    WHERE postable_id = ?
                    AND postable_type = \'ContentBlock\'
                ', [$block->id]);

                $postIds = [];
                foreach ($relatedPosts as $post) {
                    $postIds[] = $post->post_id;
                }

                if (count($postIds) === 0) {
                    continue;
                }

                $content[] = [
                    'type' => 'related',
                    'attributes' => [
                        'related_title' => $title,
                        'related_posts' => $postIds,
                    ],
                ];
            }
        }

        Post::where('id', $postId)->update([
            'content' => json_encode($content),
        ]);
    }

    private function syncOnlineMessages(int $postId, string $languageCode): void
    {
        $onlineIds = $this->legacy_db->select('
            SELECT postable_id FROM public.post_relations
            WHERE postable_type = \'OnlineItem\'
            AND post_id = ?
        ', [$postId]);
        
        $onlineIds = array_column($onlineIds, 'postable_id');

        if (count($onlineIds) === 0) {
            return;
        }

        $onlines = $this->legacy_db->select('
            SELECT * FROM public.online_items
            WHERE id IN (' . implode(',', $onlineIds) . ')
            ORDER BY time ASC
        ');

        foreach ($onlines as $online) {
            OnlineMessage::updateOrCreate(
                ['id' => $online->id],
                [
                    'language_code' => $languageCode,
                    'post_id' => $postId,
                    'published_at' => $online->time,
                    'is_key_event' => $online->key_point,
                    'outline' => $this->cleanOutline($online->title ?? ''),
                    'text' => $online->text ?? '',
                    'images' => $online->image ? [
                        'link' => $online->image,
                        'author' => '',
                        'description' => '',
                    ] : [],
                    'video_url' => $online->video_embed ?? '',
                    'video_description' => '',
                    'video_author' => '',
                    'embed_code' => $online->social_embed ?? '',
                    'embed_type' => $online->social_type ?? 'iframe',
                ]
            );
        }
    }

    private function syncThemePosts(): void
    {
        $entityType = "theme_posts";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing theme-post relations (updated after {$lastSyncTime})");

            $themePosts = $this->legacy_db->select('
                SELECT * FROM public.theme_post_relations
                WHERE updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            foreach ($themePosts as $themePost) {
                $post = Post::find($themePost->post_id);
                $theme = InvestigationTheme::find($themePost->theme_id);
                
                if ($post && $theme) {
                    $post->investigation_theme_id = $themePost->theme_id;
                    $post->save();
                    $syncedCount++;
                }
            }

            $this->info("Synced {$syncedCount} theme-post relations");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncAdmins(): void
    {
        $entityType = "admins";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing admins (updated after {$lastSyncTime})");

            $admins = $this->legacy_db->select('
                SELECT * FROM public.admins
                WHERE updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            // Получаем все связи админов с регионами
            $regionRelations = $this->legacy_db->select('
                SELECT regionable_id as admin_id, region_id 
                FROM public.region_relations
                WHERE regionable_type = \'Admin\'
            ');

            // Группируем регионы по admin_id
            $adminRegions = [];
            foreach ($regionRelations as $relation) {
                $adminRegions[$relation->admin_id][] = $relation->region_id;
            }

            $syncedCount = 0;
            foreach ($admins as $admin) {
                // Маппинг team_role_id в role_code
                $roleCode = match ($admin->team_role_id) {
                    1 => 'admin',
                    2 => 'editor',
                    3 => 'journalist',
                    default => 'journalist',
                };

                // Формируем available_languages на основе регионов или region_role
                $availableLanguages = [];
                
                // Если region_role = 'global_admin' - доступны все регионы
                if (isset($admin->region_role) && $admin->region_role === 'global_admin') {
                    $availableLanguages = ['ru' => true, 'en' => true];
                } else {
                    $regions = $adminRegions[$admin->id] ?? [];
                    
                    foreach ($regions as $regionId) {
                        if ($regionId === 1) {
                            $availableLanguages['ru'] = true;
                        } elseif ($regionId === 3) {
                            $availableLanguages['en'] = true;
                        }
                    }
                }

                // Если нет регионов, ставим по умолчанию ru
                if (empty($availableLanguages)) {
                    $availableLanguages = ['ru' => true];
                }

                // Rails использует $2a$, Laravel ожидает $2y$ - заменяем префикс
                $password = str_replace('$2a$', '$2y$', $admin->password_digest);
                
                User::updateOrCreate(
                    ['id' => $admin->id],
                    [
                        'email' => $admin->email,
                        'password' => $password,
                        'name' => $admin->name,
                        'role_code' => $roleCode,
                        'available_languages' => $availableLanguages,
                        'timezone' => $admin->timezone,
                        'created_at' => $admin->created_at,
                        'updated_at' => $admin->updated_at,
                    ]
                );
                $this->line("  → Admin ID: {$admin->id} - {$admin->name} ({$admin->email})");
                $syncedCount++;
            }

            $this->info("Synced {$syncedCount} admins");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    private function syncAdminRelations(): void
    {
        $entityType = "admin_relations";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing admin-post relations (updated after {$lastSyncTime})");

            $adminRelations = $this->legacy_db->select('
                SELECT admin_id, adminable_id as post_id, created_at, updated_at 
                FROM public.admin_relations
                WHERE adminable_type = \'Post\'
                AND updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            $totalRelations = count($adminRelations);
            $this->info("Found {$totalRelations} admin-post relations to sync");

            if ($totalRelations === 0) {
                $this->info("Synced 0 admin-post relations");
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
                return;
            }

            // Предзагружаем существующие посты и пользователей батчами (чтобы избежать огромных SQL запросов)
            $postIds = array_unique(array_column($adminRelations, 'post_id'));
            $adminIds = array_unique(array_column($adminRelations, 'admin_id'));
            
            $existingPostIds = [];
            $existingUserIds = [];
            
            // Загружаем посты батчами по 10000
            foreach (array_chunk($postIds, 10000) as $postIdsChunk) {
                $existingPostIds = array_merge(
                    $existingPostIds,
                    Post::whereIn('id', $postIdsChunk)->pluck('id')->toArray()
                );
            }
            
            // Загружаем пользователей батчами по 1000
            foreach (array_chunk($adminIds, 1000) as $adminIdsChunk) {
                $existingUserIds = array_merge(
                    $existingUserIds,
                    User::whereIn('id', $adminIdsChunk)->pluck('id')->toArray()
                );
            }
            
            $this->line("  Posts found: " . count($existingPostIds) . ", Users found: " . count($existingUserIds));

            $syncedCount = 0;
            $processedCount = 0;
            
            foreach ($adminRelations as $relation) {
                $processedCount++;
                
                // Быстрая проверка через массивы вместо запросов к БД
                if (in_array($relation->post_id, $existingPostIds) && in_array($relation->admin_id, $existingUserIds)) {
                    PostOwner::updateOrCreate(
                        [
                            'post_id' => $relation->post_id,
                            'user_id' => $relation->admin_id,
                        ],
                        [
                            'created_at' => $relation->created_at,
                            'updated_at' => $relation->updated_at,
                ]
            );
                    $syncedCount++;
                }
                
                // Выводим прогресс каждые 1000 записей
                if ($processedCount % 1000 === 0) {
                    $this->line("  Progress: {$processedCount}/{$totalRelations} processed, {$syncedCount} synced");
                }
            }

            $this->info("Synced {$syncedCount} admin-post relations (processed {$processedCount})");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Очищает outline от HTML entities и спецсимволов
     */
    private function cleanOutline(string $outline): string
    {
        $clean = html_entity_decode($outline, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = strip_tags($clean);
        $clean = trim($clean);
        return $clean;
    }

    /**
     * Нормализует HTML-содержимое текстового блока
     */
    private function normalizeHtml(string $html): string
    {
        // Удаляем символ ◀ в конце текста
        $html = preg_replace('/\s*◀\s*$/', '', $html);
        
        // Сначала очищаем пустые параграфы
        $html = $this->removeEmptyParagraphs($html);
        
        // Агрессивно удаляем ВСЕ висячие теги в начале
        $cleanStart = true;
        $maxIterations = 20;
        $iteration = 0;
        
        while ($cleanStart && $iteration < $maxIterations) {
            $cleanStart = false;
            $iteration++;
            
            // Удаляем <br></p> в начале
            if (preg_match('/^[\s\r\n\t]*<br\s*\/?><\/p>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<br\s*\/?><\/p>/is', '', $html);
                $cleanStart = true;
                continue;
            }
            
            // Удаляем </p> в начале
            if (preg_match('/^[\s\r\n\t]*<\/p>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<\/p>/is', '', $html);
                $cleanStart = true;
                continue;
            }
            
            // Удаляем <br> в начале
            if (preg_match('/^[\s\r\n\t]*<br\s*\/?>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<br\s*\/?>/is', '', $html);
                $cleanStart = true;
                continue;
            }
            
            // Удаляем любые закрывающие теги в начале
            if (preg_match('/^[\s\r\n\t]*<\/([a-z0-9]+)>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<\/([a-z0-9]+)>/is', '', $html);
                $cleanStart = true;
                continue;
            }
        }
        
        // Подсчитываем количество открывающих и закрывающих p-тегов
        $openingPCount = substr_count(strtolower($html), '<p');
        $closingPCount = substr_count(strtolower($html), '</p');
        
        // Удаляем лишние закрывающие теги p в конце
        if ($closingPCount > $openingPCount) {
            $diff = $closingPCount - $openingPCount;
            for ($i = 0; $i < $diff; $i++) {
                $html = preg_replace('/<\/p>[\s\r\n\t]*$/is', '', $html, 1);
            }
        }
        
        // Если текст не начинается с тега p и не пустой, оборачиваем его
        if (!preg_match('/^\s*<p(?:\s[^>]*)?>/i', $html) && trim($html) !== '') {
            $html = "<p>$html</p>";
        }
        
        // Агрессивно удаляем висячие теги в конце
        $cleanEnd = true;
        $maxIterations = 20;
        $iteration = 0;
        
        while ($cleanEnd && $iteration < $maxIterations) {
            $cleanEnd = false;
            $iteration++;
            
            // Удаляем <p><br> в конце без закрывающего </p>
            if (preg_match('/<p[^>]*>[\s\r\n\t]*<br\s*\/?>[\s\r\n\t]*$/is', $html)) {
                $html = preg_replace('/<p[^>]*>[\s\r\n\t]*<br\s*\/?>[\s\r\n\t]*$/is', '', $html);
                $cleanEnd = true;
                continue;
            }
            
            // Удаляем пустой <p> в конце
            if (preg_match('/<p[^>]*>[\s\r\n\t]*$/is', $html)) {
                $html = preg_replace('/<p[^>]*>[\s\r\n\t]*$/is', '', $html);
                $cleanEnd = true;
                continue;
            }
        }
        
        // Еще раз очищаем пустые параграфы после всех манипуляций
        $html = $this->removeEmptyParagraphs($html);
        
        return $html;
    }

    /**
     * Удаляет пустые параграфы
     */
    private function removeEmptyParagraphs(string $html): string
    {
        if (empty(trim($html))) {
            return $html;
        }

        // Повторяем удаление пока есть изменения
        $maxIterations = 10;
        $iteration = 0;
        
        do {
            $previousHtml = $html;
            
            // Заменяем &nbsp; на пробел
            $html = str_replace('&nbsp;', ' ', $html);
            
            // 1. Удаляем <p><br></p> и вариации
            $html = preg_replace('/<p[^>]*>\s*<br\s*\/?>\s*<\/p>/is', '', $html);
            
            // 2. Удаляем <p> с только пробелами </p>
            $html = preg_replace('/<p[^>]*>\s+<\/p>/is', '', $html);
            
            // 3. Удаляем полностью пустые <p></p>
            $html = preg_replace('/<p[^>]*><\/p>/i', '', $html);
            
            // 4. Удаляем параграфы с несколькими <br>
            $html = preg_replace('/<p[^>]*>(?:\s*<br\s*\/?>\s*)+<\/p>/is', '', $html);
            
            // 5. Удаляем параграфы с миксом пробелов и <br>
            $html = preg_replace('/<p[^>]*>[\s\r\n\t]*(?:<br\s*\/?>[\s\r\n\t]*)+<\/p>/is', '', $html);
            
            $iteration++;
        } while ($html !== $previousHtml && $iteration < $maxIterations);
        
        return $html;
    }

    private function syncCollections(): void
    {
        $entityType = "collections";
        
        try {
            SyncLog::markAsRunning($entityType);
            
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);
            
            $this->info("Syncing post collections (updated after {$lastSyncTime})");

            // Получаем ВСЕ актуальные фичеры из старой БД (для удаления устаревших)
            $allFeaturedPosts = $this->legacy_db->select('
                SELECT feature_on_mains.postable_id
                FROM public.feature_on_mains
                WHERE feature_on_mains.postable_type = \'Post\'
            ');
            
            $validPostIds = array_column($allFeaturedPosts, 'postable_id');
            
            // Удаляем фичеры которых больше нет в старой БД
            if (!empty($validPostIds)) {
                $deletedCount = CollectionPost::where('collection_code', CollectionPost::COLLECTION_CODE_FEATURE)
                    ->whereNotIn('post_id', $validPostIds)
                    ->delete();
                
                if ($deletedCount > 0) {
                    $this->line("  🗑 Deleted {$deletedCount} outdated featured posts");
                }
            }

            // Синхронизация фичеров (featured posts) из старой БД
            $featuredPosts = $this->legacy_db->select('
                SELECT feature_on_mains.*, region_relations.region_id 
                FROM public.feature_on_mains
                JOIN public.region_relations ON region_relations.regionable_id = feature_on_mains.postable_id 
                    AND region_relations.regionable_type = \'Post\'
                WHERE feature_on_mains.postable_type = \'Post\'
                AND feature_on_mains.updated_at > ?
                ORDER BY feature_on_mains.position ASC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            foreach ($featuredPosts as $featured) {
                // Определяем язык по region_id
                $languageCode = $featured->region_id === 1 ? 'ru' : 'en';
                
                // Проверяем существование поста
                $post = Post::find($featured->postable_id);
                if ($post) {
                    CollectionPost::updateOrCreate(
                        [
                            'language_code' => $languageCode,
                            'collection_code' => CollectionPost::COLLECTION_CODE_FEATURE,
                            'post_id' => $featured->postable_id,
                        ],
                        [
                            'position' => $featured->position ?? 0,
                        ]
                    );
                    $this->line("  → Featured post ID: {$featured->postable_id} (position: {$featured->position})");
                    $syncedCount++;
                }
            }

            $this->info("Synced {$syncedCount} featured posts");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            // Если таблица feature_on_mains не существует, это не ошибка
            if (str_contains($e->getMessage(), 'feature_on_mains')) {
                $this->warn("Table feature_on_mains not found in legacy database, skipping collections sync");
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
            } else {
                SyncLog::markAsFailed($entityType, $e->getMessage());
                throw $e;
            }
        }
    }
}

