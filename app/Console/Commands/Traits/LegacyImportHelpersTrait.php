<?php

namespace App\Console\Commands\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageService;
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
use App\Console\Commands\LegacyImportTermins;

/**
 * Shared helpers for legacy import commands.
 *
 * Classes using this trait must define:
 *   protected $legacy_db  — a DB connection to the legacy database
 */
trait LegacyImportHelpersTrait
{
    // -------------------------------------------------------------------------
    // Taxonomy sync
    // -------------------------------------------------------------------------

    protected function syncAdmins(): void
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

            $regionRelations = $this->legacy_db->select('
                SELECT regionable_id as admin_id, region_id
                FROM public.region_relations
                WHERE regionable_type = \'Admin\'
            ');

            $adminRegions = [];
            foreach ($regionRelations as $relation) {
                $adminRegions[$relation->admin_id][] = $relation->region_id;
            }

            $syncedCount = 0;
            $bar = $this->progressBar(count($admins));

            foreach ($admins as $admin) {
                $bar->setMessage($this->barLabel("#{$admin->id} {$admin->name}"));
                $roleCode = match ($admin->team_role_id) {
                    1 => 'admin',
                    2 => 'editor',
                    3 => 'journalist',
                    default => 'journalist',
                };

                $availableLanguages = [];

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

                if (empty($availableLanguages)) {
                    $availableLanguages = ['ru' => true];
                }

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
                $syncedCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("  Synced {$syncedCount} admin(s)");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    protected function syncCategories(int $regionId): void
    {
        $entityType = "categories_region_{$regionId}";

        try {
            SyncLog::markAsRunning($entityType);

            $languageCode = $regionId === 1 ? 'ru' : 'en';
            $lastSyncTime = SyncLog::getLastSyncTime($entityType);

            $this->info("Syncing categories for region {$regionId} (updated after {$lastSyncTime})");

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

            $categories = $this->legacy_db->select('
                SELECT * FROM public.rubrics
                WHERE id IN (' . implode(',', $categoryIds) . ')
                AND updated_at > ?
                ORDER BY id ASC
            ', [$lastSyncTime]);

            $syncedCount = 0;
            $bar = $this->progressBar(count($categories));

            foreach ($categories as $category) {
                $bar->setMessage($this->barLabel("#{$category->id} {$category->title}"));
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
                $syncedCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("  Synced {$syncedCount} categor(y/ies) for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    protected function syncAuthors(int $regionId): void
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

            $authorRoleId = $languageCode === 'ru' ? 1 : 4;
            $columnistRoleId = $languageCode === 'ru' ? 2 : 3;

            $syncedCount = 0;
            $bar = $this->progressBar(count($authors));

            foreach ($authors as $author) {
                $bar->setMessage($this->barLabel("#{$author->id} {$author->first_name} {$author->last_name}"));
                $isVisibleInPost = $author->visible_in_post ?? true;

                $roles = $this->legacy_db->select('
                    SELECT role_id FROM public.person_roles
                    WHERE person_id = ?
                ', [$author->id]);
                $roleIds = array_column($roles, 'role_id');

                $isAuthor = in_array($authorRoleId, $roleIds);
                $isColumnist = in_array($columnistRoleId, $roleIds);

                $allowedPostTypes = [];
                if ($isAuthor) {
                    array_push($allowedPostTypes, 'article', 'news', 'online');
                }
                if ($isColumnist) {
                    $allowedPostTypes[] = 'opinion';
                }

                $avatarPath = $this->downloadLegacyImage($author->id, $author->image ?? null, 'person', ImageService::TYPE_USER_PHOTO);

                Author::updateOrCreate(
                    ['id' => $author->id],
                    [
                        'language_code' => $languageCode,
                        'slug' => $author->slug,
                        'first_name' => $author->first_name,
                        'last_name' => $author->last_name,
                        'avatar' => $avatarPath,
                        'position' => $author->work_position,
                        'description' => $author->description,
                        'twitter' => $author->twitter,
                        'facebook' => $author->facebook,
                        'allowed_post_types' => $allowedPostTypes,
                        'post_types_with_hidden_author_name' => $isVisibleInPost === false ? ['news'] : [],
                        'is_author_page_hidden' => !$isAuthor,
                        'is_columnist_page_hidden' => !$isColumnist,
                    ]
                );

                $syncedCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("  Synced {$syncedCount} author(s) for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    protected function syncInvestigationThemes(int $regionId): void
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
            $position    = count($themes) * 10;
            $bar         = $this->progressBar(count($themes));

            foreach ($themes as $theme) {
                $bar->setMessage($this->barLabel("#{$theme->id} {$theme->title}"));
                $coverImagePath = $this->downloadLegacyImage($theme->id, $theme->image ?? null, 'theme', ImageService::TYPE_THEME_COVER);

                InvestigationTheme::updateOrCreate(
                    ['id' => $theme->id],
                    [
                        'language_code' => $languageCode,
                        'slug' => $theme->slug,
                        'title' => $theme->title,
                        'description' => $theme->description,
                        'position' => !empty($theme->position) ? $theme->position : $position,
                        'cover_image' => $coverImagePath,
                        'is_main' => $theme->slug === 'otraviteli-iz-fsb' && $languageCode === 'ru',
                        'created_at' => $theme->created_at,
                        'updated_at' => $theme->updated_at,
                    ]
                );
                $position--;
                $syncedCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("  Synced {$syncedCount} theme(s) for region {$regionId}");
            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            SyncLog::markAsFailed($entityType, $e->getMessage());
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Chunk data preloading
    // -------------------------------------------------------------------------

    /** Legacy data preloaded for the current chunk. Set by preloadChunkData(). */
    protected array $preloadedChunkData = [];

    /** slug → local tag id, populated by preloadTagCache(). */
    protected array $tagCache = [];

    /** Post models for the current chunk, keyed by id. */
    protected array $postModelCache = [];

    /** IDs of Author rows that exist in the new DB (populated once per sync run). */
    protected array $existingAuthorIds = [];

    /** IDs of User rows that exist in the new DB. */
    protected array $existingUserIds = [];

    /** IDs of InvestigationTheme rows that exist in the new DB. */
    protected array $existingThemeIds = [];

    /** All Termin models keyed by id.  Populated by preloadTerminCache(). */
    protected array $terminById = [];

    /**
     * Load all existing Tag slugs into $this->tagCache so per-post tag sync
     * never queries the new DB more than once per unique slug.
     * Call once before the post sync loop.
     */
    protected function preloadTagCache(): void
    {
        $this->tagCache = Tag::pluck('id', 'slug')->toArray();
    }

    /**
     * Load all Termin records into $this->terminById (id → Termin model).
     * With 5 GB of RAM this comfortably fits even a large termin table.
     * After this, syncPostContent's FK validation and resolveTermin lookups
     * are pure PHP array accesses with zero extra DB queries.
     * Call once before the post sync loop.
     */
    protected function preloadTerminCache(): void
    {
        $this->terminById = Termin::get()->keyBy('id')->all();
    }

    /**
     * Preload IDs of all existing Authors, Users and InvestigationThemes.
     * Used by relation-sync helpers to skip non-existent foreign keys without
     * individual `Model::find()` calls.
     * Call once before the post sync loop.
     */
    protected function preloadEntityIds(): void
    {
        $this->existingAuthorIds = Author::pluck('id')->toArray();
        $this->existingUserIds   = User::pluck('id')->toArray();
        $this->existingThemeIds  = InvestigationTheme::pluck('id')->toArray();
    }

    /**
     * Batch-load ALL legacy data needed for a chunk of posts.
     *
     * Without this, every per-post helper fires its own legacy-DB query → for a
     * chunk of N posts that is ~8–15 queries × N.  With preloading it is ~9
     * queries total regardless of N.
     *
     * Also pre-fetches the matching Post models from the new DB so that
     * findCachedPost() needs no additional roundtrip.
     */
    protected function preloadChunkData(array $postIds): void
    {
        $this->preloadedChunkData = [];
        $this->postModelCache     = [];

        if (empty($postIds)) {
            return;
        }

        $ids    = array_map('intval', $postIds);
        $idList = implode(',', $ids);

        // 1. Post models from the new DB
        $this->postModelCache = Post::whereIn('id', $ids)->get()->keyBy('id')->all();

        // 2. All content blocks (replaces 3 per-post queries)
        $allBlocks     = $this->legacy_db->select("
            SELECT * FROM public.content_blocks
            WHERE blockable_type = 'Post' AND blockable_id IN ({$idList})
            ORDER BY blockable_id ASC, position ASC
        ");
        $blocksByPost  = [];
        $imageBlockIds = [];
        $allBlockIds   = [];
        foreach ($allBlocks as $b) {
            $pid = (int) $b->blockable_id;
            $blocksByPost[$pid][] = $b;
            $allBlockIds[]        = (int) $b->id;
            if (in_array($b->kind, ['image', 'big_image', 'gallery'])) {
                $imageBlockIds[] = (int) $b->id;
            }
        }

        // 3. Content block images
        $imagesByBlock = [];
        if (!empty($imageBlockIds)) {
            $imgs = $this->legacy_db->select("
                SELECT * FROM public.content_block_images
                WHERE content_block_id IN (" . implode(',', $imageBlockIds) . ")
                ORDER BY position ASC
            ");
            foreach ($imgs as $img) {
                $imagesByBlock[(int) $img->content_block_id][] = $img;
            }
        }

        // 4. Related-posts inside content blocks
        $relatedByBlock = [];
        if (!empty($allBlockIds)) {
            $rels = $this->legacy_db->select("
                SELECT postable_id AS block_id, post_id
                FROM public.post_relations
                WHERE postable_type = 'ContentBlock'
                  AND postable_id IN (" . implode(',', $allBlockIds) . ")
            ");
            foreach ($rels as $r) {
                $relatedByBlock[(int) $r->block_id][] = (int) $r->post_id;
            }
        }

        // 5. Online items (for Post::Online)
        $onlineItemsByPost = [];
        $onlineJoin = $this->legacy_db->select("
            SELECT pr.post_id, oi.*
            FROM public.post_relations pr
            JOIN public.online_items oi ON oi.id = pr.postable_id
            WHERE pr.postable_type = 'OnlineItem'
              AND pr.post_id IN ({$idList})
            ORDER BY oi.time ASC
        ");
        foreach ($onlineJoin as $row) {
            $onlineItemsByPost[(int) $row->post_id][] = $row;
        }

        // 6. Tag relations + legacy tags
        // Use LIKE 'Post%' to capture all Post subtypes (Post::Article, Post::News, etc.)
        $tagRelsByPost  = [];
        $tagIds         = [];
        $tagRels = $this->legacy_db->select("
            SELECT tagable_id AS post_id, tag_id
            FROM public.tag_relations
            WHERE tagable_id IN ({$idList}) AND tagable_type LIKE 'Post%'
        ");
        foreach ($tagRels as $r) {
            $tagRelsByPost[(int) $r->post_id][] = (int) $r->tag_id;
            $tagIds[] = (int) $r->tag_id;
        }
        $legacyTagsById = [];
        if (!empty($tagIds)) {
            $tags = $this->legacy_db->select("
                SELECT * FROM public.tags
                WHERE id IN (" . implode(',', array_unique($tagIds)) . ")
            ");
            foreach ($tags as $t) {
                $legacyTagsById[(int) $t->id] = $t;
            }
        }

        // 7. Person (author) relations — UNION avoids ambiguous CASE on nullable columns
        $personRelsByPost = [];
        $personRels = $this->legacy_db->select("
            SELECT person_id, personable2_id AS post_id
            FROM public.person_relations
            WHERE personable2_type = 'Post' AND personable2_id IN ({$idList})
            UNION ALL
            SELECT person_id, personable3_id AS post_id
            FROM public.person_relations
            WHERE personable3_type = 'Post' AND personable3_id IN ({$idList})
        ");
        foreach ($personRels as $r) {
            $personRelsByPost[(int) $r->post_id][] = (int) $r->person_id;
        }

        // 8. Theme post relations
        $themeRelsByPost = [];
        $themeRels = $this->legacy_db->select("
            SELECT post_id, theme_id
            FROM public.theme_post_relations
            WHERE post_id IN ({$idList})
        ");
        foreach ($themeRels as $r) {
            $themeRelsByPost[(int) $r->post_id] = (int) $r->theme_id;
        }

        // 9. Admin relations
        $adminRelsByPost = [];
        $adminRels = $this->legacy_db->select("
            SELECT admin_id, adminable_id AS post_id, created_at, updated_at
            FROM public.admin_relations
            WHERE adminable_type = 'Post' AND adminable_id IN ({$idList})
        ");
        foreach ($adminRels as $r) {
            $adminRelsByPost[(int) $r->post_id][] = $r;
        }

        // 10. Featured (feature_on_mains) with region
        $featuredByPost = [];
        try {
            $featuredRows = $this->legacy_db->select("
                SELECT fom.postable_id AS post_id, fom.position, rr.region_id
                FROM public.feature_on_mains fom
                JOIN public.region_relations rr
                  ON rr.regionable_id = fom.postable_id AND rr.regionable_type = 'Post'
                WHERE fom.postable_type = 'Post' AND fom.postable_id IN ({$idList})
            ");
            foreach ($featuredRows as $r) {
                $featuredByPost[(int) $r->post_id][(int) $r->region_id] = $r;
            }
        } catch (\Throwable) {
            // feature_on_mains may not exist on all environments
        }

        $this->preloadedChunkData = [
            'blocks_by_post'       => $blocksByPost,
            'images_by_block'      => $imagesByBlock,
            'related_posts_by_block' => $relatedByBlock,
            'online_items_by_post' => $onlineItemsByPost,
            'tag_rels_by_post'     => $tagRelsByPost,
            'legacy_tags_by_id'    => $legacyTagsById,
            'person_rels_by_post'  => $personRelsByPost,
            'theme_rels_by_post'   => $themeRelsByPost,
            'admin_rels_by_post'   => $adminRelsByPost,
            'featured_by_post'     => $featuredByPost,
        ];
    }

    /**
     * Return the Post model from the per-chunk cache, falling back to a DB query.
     * This avoids repeated Post::find() calls across the multiple relation-sync
     * helpers called for the same post.
     */
    protected function findCachedPost(int $postId): ?Post
    {
        if (isset($this->postModelCache[$postId])) {
            return $this->postModelCache[$postId];
        }
        $post = Post::find($postId);
        if ($post) {
            $this->postModelCache[$postId] = $post;
        }
        return $post;
    }

    // -------------------------------------------------------------------------
    // Post relation helpers
    // -------------------------------------------------------------------------

    protected function syncPostAuthorsForPost(int $postId): void
    {
        // Use preloaded data or fall back to individual legacy-DB query
        if (array_key_exists('person_rels_by_post', $this->preloadedChunkData)) {
            $personIds = $this->preloadedChunkData['person_rels_by_post'][$postId] ?? [];
        } else {
            $rows      = $this->legacy_db->select('
                SELECT person_id FROM public.person_relations
                WHERE (personable2_type = \'Post\' AND personable2_id = ?)
                   OR (personable3_type = \'Post\' AND personable3_id = ?)
            ', [$postId, $postId]);
            $personIds = array_column($rows, 'person_id');
        }

        if (empty($personIds)) {
            return;
        }

        $post = $this->findCachedPost($postId);
        if (!$post) {
            return;
        }

        foreach ($personIds as $personId) {
            // Skip authors not yet imported (uses preloaded ID set — no DB hit)
            if (!in_array($personId, $this->existingAuthorIds, true)) {
                // Fall back to real DB check if the preloaded set is empty (single-post restore)
                if (!empty($this->existingAuthorIds) || !Author::find($personId)) {
                    continue;
                }
            }
            if ($post->type === 'opinion') {
                $post->columnist_id = $personId;
                $post->save();
            } else {
                PostAuthor::updateOrCreate(['post_id' => $postId, 'author_id' => $personId]);
            }
        }
    }

    protected function syncThemePostForPost(int $postId): void
    {
        if (array_key_exists('theme_rels_by_post', $this->preloadedChunkData)) {
            $themeId = $this->preloadedChunkData['theme_rels_by_post'][$postId] ?? null;
        } else {
            $row     = $this->legacy_db->selectOne('
                SELECT theme_id FROM public.theme_post_relations WHERE post_id = ? LIMIT 1
            ', [$postId]);
            $themeId = $row?->theme_id;
        }

        if (!$themeId) {
            return;
        }

        // Skip themes not yet imported
        if (!empty($this->existingThemeIds) && !in_array($themeId, $this->existingThemeIds, true)) {
            return;
        }

        $post = $this->findCachedPost($postId);
        if ($post) {
            $post->investigation_theme_id = $themeId;
            $post->save();
        }
    }

    protected function syncAdminRelationsForPost(int $postId): void
    {
        if (array_key_exists('admin_rels_by_post', $this->preloadedChunkData)) {
            $relations = $this->preloadedChunkData['admin_rels_by_post'][$postId] ?? [];
        } else {
            $relations = $this->legacy_db->select('
                SELECT admin_id, created_at, updated_at
                FROM public.admin_relations
                WHERE adminable_type = \'Post\' AND adminable_id = ?
            ', [$postId]);
        }

        foreach ($relations as $relation) {
            // Skip admins not yet imported
            if (!empty($this->existingUserIds) && !in_array($relation->admin_id, $this->existingUserIds, true)) {
                continue;
            }
            PostOwner::updateOrCreate(
                ['post_id' => $postId, 'user_id' => $relation->admin_id],
                ['created_at' => $relation->created_at, 'updated_at' => $relation->updated_at]
            );
        }
    }

    protected function syncCollectionsForPost(int $postId, int $regionId, string $languageCode): void
    {
        if (array_key_exists('featured_by_post', $this->preloadedChunkData)) {
            $featured = $this->preloadedChunkData['featured_by_post'][$postId][$regionId] ?? null;
        } else {
            $featured = $this->legacy_db->selectOne('
                SELECT position FROM public.feature_on_mains
                JOIN public.region_relations ON region_relations.regionable_id = feature_on_mains.postable_id
                    AND region_relations.regionable_type = \'Post\'
                WHERE feature_on_mains.postable_type = \'Post\'
                AND feature_on_mains.postable_id = ?
                AND region_relations.region_id = ?
            ', [$postId, $regionId]);
        }

        if (!$featured) {
            return;
        }

        $post = $this->findCachedPost($postId);
        if (!$post) {
            return;
        }

        CollectionPost::updateOrCreate(
            [
                'language_code'   => $languageCode,
                'collection_code' => CollectionPost::COLLECTION_CODE_FEATURE,
                'post_id'         => $postId,
            ],
            ['position' => $featured->position ?? 0]
        );
    }

    protected function syncCollectionsCleanup(): void
    {
        $entityType = "collections";
        try {
            SyncLog::markAsRunning($entityType);

            $allFeaturedPosts = $this->legacy_db->select('
                SELECT postable_id FROM public.feature_on_mains
                WHERE postable_type = \'Post\'
            ');
            $validPostIds = array_column($allFeaturedPosts, 'postable_id');

            if (!empty($validPostIds)) {
                $deletedCount = CollectionPost::where('collection_code', CollectionPost::COLLECTION_CODE_FEATURE)
                    ->whereNotIn('post_id', $validPostIds)
                    ->delete();
                if ($deletedCount > 0) {
                    $this->line("  Deleted {$deletedCount} outdated featured posts");
                }
            }

            SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'feature_on_mains')) {
                $this->warn("Table feature_on_mains not found, skipping collections cleanup");
                SyncLog::markAsCompleted($entityType, now()->format('Y-m-d H:i:s'));
            } else {
                SyncLog::markAsFailed($entityType, $e->getMessage());
                throw $e;
            }
        }
    }

    protected function syncPostTags(int $postId, string $languageCode): void
    {
        $currentPost = $this->findCachedPost($postId);
        if (!$currentPost) {
            return;
        }

        // Use preloaded data when available, otherwise query individually
        if (array_key_exists('tag_rels_by_post', $this->preloadedChunkData)) {
            $tagIds        = $this->preloadedChunkData['tag_rels_by_post'][$postId] ?? [];
            $legacyTagsMap = $this->preloadedChunkData['legacy_tags_by_id'] ?? [];
        } else {
            // Use LIKE 'Post%' to capture all Post subtypes (Post::Article, Post::News, etc.)
            $dbTagRelations = $this->legacy_db->select('
                SELECT tag_id FROM public.tag_relations
                WHERE tagable_id = ? AND tagable_type LIKE \'Post%\'
            ', [$postId]);
            $tagIds = array_column($dbTagRelations, 'tag_id');
            $legacyTagsMap = [];
            if (!empty($tagIds)) {
                $dbTags = $this->legacy_db->select('
                    SELECT * FROM public.tags WHERE id IN (' . implode(',', $tagIds) . ')
                ');
                foreach ($dbTags as $t) {
                    $legacyTagsMap[(int) $t->id] = $t;
                }
            }
        }

        if (empty($tagIds)) {
            return;
        }

        $localTagIds = [];
        foreach ($tagIds as $tagId) {
            $dbTag = $legacyTagsMap[(int) $tagId] ?? null;
            if (!$dbTag) {
                continue;
            }

            // Check in-memory cache first — avoids a DB roundtrip per unique slug
            if (!isset($this->tagCache[$dbTag->slug])) {
                $tag = Tag::where('slug', $dbTag->slug)->first();
                if (!$tag) {
                    $tag = Tag::create([
                        'language_code' => $languageCode,
                        'id'            => $dbTag->id,
                        'title'         => $dbTag->title,
                        'slug'          => $dbTag->slug,
                    ]);
                }
                $this->tagCache[$dbTag->slug] = $tag->id;
            }

            $localTagIds[] = $this->tagCache[$dbTag->slug];
        }

        if (!empty($localTagIds)) {
            $currentPost->tags()->syncWithoutDetaching($localTagIds);
        }
    }

    // -------------------------------------------------------------------------
    // Post content sync
    // -------------------------------------------------------------------------

    protected function syncPostContent(int $postId, string $languageCode): void
    {
        $currentPost = $this->findCachedPost($postId);
        if (!$currentPost) {
            return;
        }

        // Use preloaded blocks when available; otherwise fall back to a SINGLE
        // query that fetches all block kinds at once (vs. the previous 3 queries).
        if (array_key_exists('blocks_by_post', $this->preloadedChunkData)) {
            $allBlocks = $this->preloadedChunkData['blocks_by_post'][$postId] ?? [];
        } else {
            $allBlocks = $this->legacy_db->select("
                SELECT * FROM public.content_blocks
                WHERE blockable_type = 'Post' AND blockable_id = ?
                ORDER BY position ASC
            ", [$postId]);
        }

        // --- Build $termins map (human_id => description text) ---
        $termins = [];
        foreach ($allBlocks as $b) {
            if ($b->kind !== 'term') {
                continue;
            }
            $txt = json_decode($b->content ?? '{}')->text ?? '';
            if (!isset($b->human_id) || $b->human_id === '' || $txt === '') {
                continue;
            }
            $txt = $this->normalizeTerminDescription($txt);
            if ($txt === '') {
                continue;
            }
            $termins[$b->human_id] = $txt;
        }

        // --- Build $templates map (human_id => block data) ---
        $templates      = [];
        $templateKinds  = ['image', 'big_image', 'gallery', 'video', 'quote'];

        foreach ($allBlocks as $dbTemplate) {
            if (!in_array($dbTemplate->kind, $templateKinds)) {
                continue;
            }

            if (in_array($dbTemplate->kind, ['image', 'big_image', 'gallery'])) {
                if (array_key_exists('images_by_block', $this->preloadedChunkData)) {
                    $images = $this->preloadedChunkData['images_by_block'][(int) $dbTemplate->id] ?? [];
                } else {
                    $images = $this->legacy_db->select('
                        SELECT * FROM public.content_block_images
                        WHERE content_block_id = ? ORDER BY position ASC
                    ', [$dbTemplate->id]);
                }

                if (empty($images)) {
                    continue;
                }

                $templates[$dbTemplate->human_id]['type'] = 'images';
                foreach ($images as $image) {
                    $imagePath = $this->downloadLegacyImage(
                        $image->id, $image->image ?? null,
                        'content_block/image', ImageService::TYPE_CONTENT_IMAGE
                    );
                    $templates[$dbTemplate->human_id]['attributes']['images'][] = [
                        'id'          => (string) $image->id,
                        'link'        => $imagePath,
                        'author'      => $image->credit ?? '',
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
                    'video_url'         => $dbTemplateContent->video_embed ?? '',
                    'video_description' => $dbTemplate->caption ?? '',
                    'video_author'      => $dbTemplate->credit ?? '',
                ];
            }

            if ($dbTemplate->kind === 'quote') {
                $dbTemplateContent = json_decode($dbTemplate->content);
                if (isset($dbTemplateContent->quote) && $dbTemplateContent->quote === '') {
                    continue;
                }
                $templates[$dbTemplate->human_id]['type'] = 'quote';
                $templates[$dbTemplate->human_id]['attributes'] = [
                    'quote'        => $dbTemplateContent->quote ?? '',
                    'quote_author' => '',
                ];
            }
        }

        // --- Process main content blocks ---
        $content     = [];
        $mainKinds   = ['number', 'text', 'social', 'related_posts', 'audio', 'iframe'];
        $socialTypes = ['iframe', 'telegram', 'twitter', 'facebook', 'instagram', 'vk', 'ok', 'audio'];

        foreach ($allBlocks as $block) {
            if (!in_array($block->kind, $mainKinds)) {
                continue;
            }

            if ($block->kind === 'number') {
                if (!isset($block->title) || $block->title === '') {
                    continue;
                }
                $content[] = [
                    'type'       => 'outline',
                    'attributes' => ['outline' => $this->cleanOutline($block->title)],
                ];
            }

            if ($block->kind === 'text') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->text) || $blockContent->text === '') {
                    continue;
                }
                $text = $blockContent->text;

                $text = preg_replace_callback('/<a\s+href="\{\{term_([^}]+)\}\}"[^>]*>(.*?)<\/a\s*>/is', function ($matches) use ($termins, $currentPost) {
                    $terminCode        = "{{term_" . $matches[1] . "}}";
                    $terminDescription = $termins[$terminCode] ?? '';
                    $displayWord       = trim(preg_replace('/\s+/', ' ', strip_tags($matches[2])));

                    if ($displayWord === '' || $terminDescription === '') {
                        return $matches[0];
                    }

                    $termin = $this->resolveTermin($displayWord, $terminDescription, $currentPost);
                    if (!$termin) {
                        return $matches[0];
                    }

                    return '<span class="termin" data-id="' . $termin->id . '">' . e($displayWord) . '</span>';
                }, $text);

                // Step 1: scan for REAL <h3> (existing in original markup),
                // legacy inline wp-content images, and template placeholders.
                // transformStrongParagraphsToH3 runs later, per text-chunk, so
                // <p><strong>…</strong></p> headings are NOT added to the outline.
                $matches = [];

                preg_match_all('/<h3>(.*?)<\/h3>/i', $text, $h3Matches, PREG_OFFSET_CAPTURE);
                foreach ($h3Matches[0] as $index => $match) {
                    $matches[] = [
                        'type'      => 'h3',
                        'content'   => $h3Matches[1][$index][0],
                        'fullMatch' => $match[0],
                        'offset'    => $match[1],
                        'length'    => strlen($match[0]),
                    ];
                }

                preg_match_all('/\{\{([^}]+)\}\}/i', $text, $tmplMatches, PREG_OFFSET_CAPTURE);
                foreach ($tmplMatches[0] as $match) {
                    $matches[] = [
                        'type'      => 'template',
                        'fullMatch' => $match[0],
                        'offset'    => $match[1],
                        'length'    => strlen($match[0]),
                    ];
                }

                preg_match_all(
                    '/(?:<a\b[^>]*>\s*)?<img\b(?=[^>]*\bsrc=["\'](?:https?:\/\/theins\.ru)?\/wp-content\/[^"\']+["\'])[^>]*\/?>(?:\s*<\/a>)?/is',
                    $text,
                    $imageMatches,
                    PREG_OFFSET_CAPTURE
                );
                foreach ($imageMatches[0] as $match) {
                    $matches[] = [
                        'type'      => 'legacy_wp_image',
                        'fullMatch' => $match[0],
                        'offset'    => $match[1],
                        'length'    => strlen($match[0]),
                    ];
                }

                preg_match_all(
                    '/\[embed\](.*?)\[\/embed\]/is',
                    $text,
                    $embedMatches,
                    PREG_OFFSET_CAPTURE
                );
                foreach ($embedMatches[0] as $match) {
                    $matches[] = [
                        'type'      => 'legacy_embed',
                        'fullMatch' => $match[0],
                        'offset'    => $match[1],
                        'length'    => strlen($match[0]),
                    ];
                }

                usort($matches, fn ($a, $b) => $a['offset'] - $b['offset']);

                // Step 2: split into blocks; apply transformStrongParagraphsToH3
                // only on text chunks (not on outline or template markers).
                if (empty($matches)) {
                    $content[] = ['type' => 'text', 'attributes' => ['text' => $this->normalizeHtml($this->transformLegacyTextMarkup($text))]];
                } else {
                    $currentPosition = 0;
                    foreach ($matches as $match) {
                        $textBefore = substr($text, $currentPosition, $match['offset'] - $currentPosition);
                        if (!empty(trim($textBefore))) {
                            $content[] = ['type' => 'text', 'attributes' => ['text' => $this->normalizeHtml($this->transformLegacyTextMarkup($textBefore))]];
                        }
                        if ($match['type'] === 'h3') {
                            $content[] = ['type' => 'outline', 'attributes' => ['outline' => $this->cleanOutline($match['content'])]];
                        } elseif ($match['type'] === 'legacy_wp_image') {
                            $imageBlock = $this->buildLegacyWpContentImageBlock($match['fullMatch'], $postId);
                            if ($imageBlock !== null) {
                                $content[] = $imageBlock;
                            }
                        } elseif ($match['type'] === 'legacy_embed') {
                            $embedBlock = $this->buildLegacyEmbedBlock($match['fullMatch']);
                            if ($embedBlock !== null) {
                                $content[] = $embedBlock;
                            }
                        } elseif (isset($templates[$match['fullMatch']])) {
                            $content[] = $templates[$match['fullMatch']];
                        }
                        $currentPosition = $match['offset'] + $match['length'];
                    }
                    $textAfter = substr($text, $currentPosition);
                    if (!empty(trim($textAfter))) {
                        $content[] = ['type' => 'text', 'attributes' => ['text' => $this->normalizeHtml($this->transformLegacyTextMarkup($textAfter))]];
                    }
                }
            }

            if ($block->kind === 'social') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->social_embed) || $blockContent->social_embed === '') {
                    continue;
                }
                $fallbackType = isset($blockContent->social_type) && in_array($blockContent->social_type, $socialTypes)
                    ? $blockContent->social_type
                    : 'iframe';
                $content[] = [
                    'type' => 'embed',
                    'attributes' => $this->buildEmbedAttributes((string) $blockContent->social_embed, $fallbackType),
                ];
            }

            if ($block->kind === 'audio') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->audio_embed) || $blockContent->audio_embed === '') {
                    continue;
                }
                $content[] = ['type' => 'embed', 'attributes' => ['embed_code' => $blockContent->audio_embed, 'embed_type' => 'audio']];
            }

            if ($block->kind === 'iframe') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->iframe) || $blockContent->iframe === '') {
                    continue;
                }
                $content[] = ['type' => 'embed', 'attributes' => ['embed_code' => $blockContent->iframe, 'embed_type' => 'iframe']];
            }

            if ($block->kind === 'related_posts') {
                $blockContent = json_decode($block->content);
                $title        = $blockContent->related_posts_title ?? '';

                if (array_key_exists('related_posts_by_block', $this->preloadedChunkData)) {
                    $relatedPostIds = $this->preloadedChunkData['related_posts_by_block'][(int) $block->id] ?? [];
                } else {
                    $rows           = $this->legacy_db->select("
                        SELECT post_id FROM public.post_relations
                        WHERE postable_id = ? AND postable_type = 'ContentBlock'
                    ", [$block->id]);
                    $relatedPostIds = array_column($rows, 'post_id');
                }

                if (empty($relatedPostIds)) {
                    continue;
                }
                $content[] = ['type' => 'related', 'attributes' => ['related_title' => $title, 'related_posts' => $relatedPostIds]];
            }
        }

        Post::where('id', $postId)->update(['content' => json_encode($content)]);

        // Post::where()->update() bypasses Eloquent events — sync termins manually.
        $terminIds = [];
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                preg_match_all('/data-id="(\d+)"/', $block['attributes']['text'] ?? '', $m);
                $terminIds = array_merge($terminIds, $m[1]);
            }
        }
        $terminIds = array_unique(array_map('intval', $terminIds));
        if (!empty($terminIds)) {
            // Guard against stale IDs.  When the in-memory cache is populated
            // we avoid a DB query entirely; otherwise fall back to a DB check.
            if (!empty($this->terminById)) {
                $validTerminIds = array_values(
                    array_filter($terminIds, fn ($id) => isset($this->terminById[$id]))
                );
            } else {
                $validTerminIds = Termin::whereIn('id', $terminIds)->pluck('id')->toArray();
            }
            if (!empty($validTerminIds)) {
                $currentPost->termins()->syncWithoutDetaching($validTerminIds);
            }
        }
    }

    /**
     * Resolve (find or create) a Termin record for a given display word and description.
     * Override this method in subclasses to change the resolution strategy (e.g. use md5 cache).
     */
    protected function normalizeTerminDescription(string $text): string
    {
        return LegacyImportTermins::normalizeDescription($text);
    }

    protected function resolveTermin(string $displayWord, string $terminDescription, Post $post): ?Termin
    {
        $termin = Termin::where('description', $terminDescription)
            ->where('language_code', $post->language_code)
            ->first();

        if (!$termin) {
            $termin = Termin::create([
                'language_code' => $post->language_code,
                'termin'        => Termin::uniqueName($displayWord),
                'description'   => $terminDescription,
            ]);
        }

        // Keep the in-memory cache warm so syncPostContent's FK validation
        // finds this termin whether it was just created or already existed.
        $this->terminById[$termin->id] = $termin;

        return $termin;
    }

    protected function syncOnlineMessages(int $postId, string $languageCode): void
    {
        // Use preloaded data (already JOINed with online_items) when available
        if (array_key_exists('online_items_by_post', $this->preloadedChunkData)) {
            $onlines = $this->preloadedChunkData['online_items_by_post'][$postId] ?? [];
            if (empty($onlines)) {
                return;
            }
        } else {
            $onlineIds = $this->legacy_db->select('
                SELECT postable_id FROM public.post_relations
                WHERE postable_type = \'OnlineItem\'
                AND post_id = ?
            ', [$postId]);

            $onlineIds = array_column($onlineIds, 'postable_id');

            if (empty($onlineIds)) {
                return;
            }

            $onlines = $this->legacy_db->select('
                SELECT * FROM public.online_items
                WHERE id IN (' . implode(',', $onlineIds) . ')
                ORDER BY time ASC
            ');
        }

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
                        'id' => (string) $online->id,
                        'link' => $this->downloadLegacyImage($online->id, $online->image, 'online_item', ImageService::TYPE_ONLINE_IMAGE),
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

    // -------------------------------------------------------------------------
    // Single-post restore (shared across both full-sync commands)
    // -------------------------------------------------------------------------

    protected function restoreSinglePost(int $legacyPostId): int
    {
        $this->info("Restoring single post (legacy ID): {$legacyPostId}");

        $regions = $this->legacy_db->select('
            SELECT region_id FROM public.region_relations
            WHERE regionable_type = \'Post\' AND regionable_id = ?
        ', [$legacyPostId]);

        if (empty($regions)) {
            $this->error("Post {$legacyPostId} not found in legacy region_relations.");
            return 1;
        }

        $regionId     = (int) $regions[0]->region_id;
        $languageCode = $regionId === 1 ? 'ru' : 'en';

        $posts = $this->legacy_db->select('
            SELECT posts.*, rubric_relations.rubric_id FROM public.posts
            LEFT JOIN (
                SELECT rubricable_id, MIN(rubric_id) as rubric_id
                FROM public.rubric_relations
                WHERE rubricable_type = \'Post\'
                GROUP BY rubricable_id
            ) as rubric_relations ON rubric_relations.rubricable_id = posts.id
            WHERE posts.id = ?
        ', [$legacyPostId]);

        if (empty($posts)) {
            $this->error("Post {$legacyPostId} not found in legacy posts table.");
            return 1;
        }

        $post = $posts[0];

        try {
            $imagePath = $this->downloadLegacyImage(
                $post->id,
                $post->preview_image ?? $post->detail_image ?? null,
                'post',
                ImageService::TYPE_POST_COVER
            );

            Post::updateOrCreate(
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
                        default => 'default',
                    },
                    'image' => $imagePath,
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

            if ($post->type === 'Post::Online') {
                $this->syncOnlineMessages($post->id, $languageCode);
            } else {
                $this->syncPostContent($post->id, $languageCode);
            }
            $this->syncPostTags($post->id, $languageCode);
            $this->syncPostAuthorsForPost($post->id);
            $this->syncThemePostForPost($post->id);
            $this->syncAdminRelationsForPost($post->id);
            $this->syncCollectionsForPost($post->id, $regionId, $languageCode);

            $this->info("Post {$legacyPostId} restored successfully.");
            return 0;
        } catch (\Exception $e) {
            $this->error('Restore failed: ' . $e->getMessage());
            Log::error('Legacy single post restore failed: ' . $e->getMessage(), [
                'post_id' => $legacyPostId,
                'trace'   => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    // -------------------------------------------------------------------------
    // Image download
    // -------------------------------------------------------------------------

    protected function downloadLegacyImage(int $id, ?string $legacyFilename, string $legacySlug, string $imageType): ?string
    {
        if (empty($legacyFilename)) {
            return null;
        }

        $url = 'https://insidertexts.com/storage/' . $legacySlug . '/' . $id . '/' . $legacyFilename;

        try {
            $targetPath = ImageService::getImagePath($id, $imageType, ImageService::SIZE_ORIGINAL)
                . '/' . $legacyFilename;

            Storage::disk('public')->makeDirectory(dirname($targetPath));

            $fullPath = Storage::disk('public')->path($targetPath);

            $response = Http::timeout(30)
                ->withOptions(['sink' => $fullPath])
                ->get($url);

            if (!$response->successful()) {
                @unlink($fullPath);
                Log::warning("Не удалось загрузить {$imageType} #{$id}: HTTP {$response->status()}");
                return null;
            }

            return $targetPath;
        } catch (\Exception $e) {
            Log::warning("Ошибка загрузки {$imageType} #{$id}: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // HTML helpers
    // -------------------------------------------------------------------------

    protected function cleanOutline(string $outline): string
    {
        $clean = html_entity_decode($outline, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = strip_tags($clean);
        $clean = trim($clean);
        return $clean;
    }

    protected function normalizeHtml(string $html): string
    {
        $html = preg_replace('/\s*◀\s*$/', '', $html);
        $html = $this->removeEmptyParagraphs($html);

        // Remove one or more leading <br> tags at the very start of a <p>.
        // e.g. <p><br>Текст</p>  →  <p>Текст</p>
        $html = preg_replace('/<p([^>]*)>(\s*<br\s*\/?>\s*)+/i', '<p$1>', $html);

        $cleanStart  = true;
        $maxIterations = 20;
        $iteration   = 0;

        while ($cleanStart && $iteration < $maxIterations) {
            $cleanStart = false;
            $iteration++;

            if (preg_match('/^[\s\r\n\t]*<br\s*\/?><\/p>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<br\s*\/?><\/p>/is', '', $html);
                $cleanStart = true;
                continue;
            }

            if (preg_match('/^[\s\r\n\t]*<\/p>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<\/p>/is', '', $html);
                $cleanStart = true;
                continue;
            }

            if (preg_match('/^[\s\r\n\t]*<br\s*\/?>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<br\s*\/?>/is', '', $html);
                $cleanStart = true;
                continue;
            }

            if (preg_match('/^[\s\r\n\t]*<\/([a-z0-9]+)>/is', $html)) {
                $html = preg_replace('/^[\s\r\n\t]*<\/([a-z0-9]+)>/is', '', $html);
                $cleanStart = true;
                continue;
            }
        }

        $openingPCount = substr_count(strtolower($html), '<p');
        $closingPCount = substr_count(strtolower($html), '</p');

        if ($closingPCount > $openingPCount) {
            $diff = $closingPCount - $openingPCount;
            for ($i = 0; $i < $diff; $i++) {
                $html = preg_replace('/<\/p>[\s\r\n\t]*$/is', '', $html, 1);
            }
        }

        $html = $this->wrapLooseParagraphsInPTags($html);

        $cleanEnd      = true;
        $maxIterations = 20;
        $iteration     = 0;

        while ($cleanEnd && $iteration < $maxIterations) {
            $cleanEnd = false;
            $iteration++;

            if (preg_match('/<p[^>]*>[\s\r\n\t]*<br\s*\/?>[\s\r\n\t]*$/is', $html)) {
                $html = preg_replace('/<p[^>]*>[\s\r\n\t]*<br\s*\/?>[\s\r\n\t]*$/is', '', $html);
                $cleanEnd = true;
                continue;
            }

            if (preg_match('/<p[^>]*>[\s\r\n\t]*$/is', $html)) {
                $html = preg_replace('/<p[^>]*>[\s\r\n\t]*$/is', '', $html);
                $cleanEnd = true;
                continue;
            }
        }

        $html = $this->removeEmptyParagraphs($html);

        return $html;
    }

    protected function wrapLooseParagraphsInPTags(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        // Isolate block-level fragments so mixed content like
        // "plain text <blockquote>...</blockquote> more text" gets split into
        // separate chunks before we wrap loose text in <p> tags.
        $html = preg_replace(
            '/\s*(<(?:p|h[1-6]|ul|ol|blockquote|div|table|figure|pre)\b[\s\S]*?<\/(?:p|h[1-6]|ul|ol|blockquote|div|table|figure|pre)>)\s*/i',
            "\n\n$1\n\n",
            $html
        );

        $parts = preg_split('/(?:\r?\n\s*){2,}/', $html);
        if ($parts === false) {
            return $html;
        }

        $wrapped = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // Leave already block-level fragments untouched; wrap loose inline/text fragments.
            if (preg_match('/^<(?:p|h[1-6]|ul|ol|blockquote|div|table|figure|pre)(?:\s[^>]*)?>/i', $part)) {
                $wrapped[] = $part;
                continue;
            }

            $wrapped[] = "<p>{$part}</p>";
        }

        return implode('', $wrapped);
    }

    protected function removeEmptyParagraphs(string $html): string
    {
        if (empty(trim($html))) {
            return $html;
        }

        $maxIterations = 10;
        $iteration     = 0;

        do {
            $previousHtml = $html;

            // Replace &nbsp; and its numeric equivalents with a plain space so
            // the whitespace-only patterns below can catch them.
            $html = str_replace(['&nbsp;', '&#160;', '&#xA0;', '&#xa0;'], ' ', $html);

            $html = preg_replace('/<p[^>]*>\s*<br\s*\/?>\s*<\/p>/is', '', $html);
            $html = preg_replace('/<p[^>]*>\s+<\/p>/is', '', $html);
            $html = preg_replace('/<p[^>]*><\/p>/i', '', $html);
            $html = preg_replace('/<p[^>]*>(?:\s*<br\s*\/?>\s*)+<\/p>/is', '', $html);
            $html = preg_replace('/<p[^>]*>[\s\r\n\t]*(?:<br\s*\/?>[\s\r\n\t]*)+<\/p>/is', '', $html);

            $iteration++;
        } while ($html !== $previousHtml && $iteration < $maxIterations);

        return $html;
    }

    protected function transformStrongParagraphsToH3(string $html): string
    {
        return preg_replace_callback(
            '/<p(\s[^>]*)?>\s*<strong(\s[^>]*)?>(.*?)<\/strong>\s*<\/p>/is',
            function (array $m): string {
                $inner = $m[3];
                if (str_contains($inner, '<')) {
                    return $m[0];
                }
                $inner = preg_replace('/\s+style\s*=\s*["\'][^"\']*["\']/i', '', $inner);
                return '<h3>' . $inner . '</h3>';
            },
            $html
        );
    }

    protected function transformLegacyIndentedQuotesToBlockquotes(string $html): string
    {
        return preg_replace(
            '/<p\b([^>]*)\bstyle\s*=\s*(["\'])[^"\']*\bpadding-left\s*:\s*\d+px\b[^"\']*\2([^>]*)>\s*<em(?:\s[^>]*)?>(.*?)<\/em>\s*<\/p>/is',
            '<blockquote>$4</blockquote>',
            $html
        );
    }

    protected function transformLegacyTextMarkup(string $html): string
    {
        $html = $this->transformLegacyIndentedQuotesToBlockquotes($html);
        return $this->transformStrongParagraphsToH3($html);
    }

    protected function buildLegacyEmbedBlock(string $html): ?array
    {
        if (!preg_match('/\[embed\](.*?)\[\/embed\]/is', $html, $matches)) {
            return null;
        }

        $embedSource = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($embedSource === '') {
            return null;
        }

        return [
            'type' => 'embed',
            'attributes' => $this->buildEmbedAttributes($embedSource),
        ];
    }

    protected function buildEmbedAttributes(string $embedCode, ?string $fallbackType = null): array
    {
        $embedCode = trim($embedCode);
        $embedType = $this->detectLegacyEmbedType($embedCode) ?? ($fallbackType ?: 'iframe');

        if ($this->isPlainUrl($embedCode) && $this->embedTypeRequiresHtmlCode($embedType)) {
            $embedCode = $this->wrapUrlAsAnchor($embedCode);
        }

        return [
            'embed_code' => $embedCode,
            'embed_type' => $embedType,
        ];
    }

    protected function detectLegacyEmbedType(string $embedCode): ?string
    {
        $embedCode = mb_strtolower($embedCode);

        if (str_contains($embedCode, '<iframe')) {
            return 'iframe';
        }

        if (str_contains($embedCode, 'instagram.com')) {
            return 'instagram';
        }

        if (str_contains($embedCode, 't.me') || str_contains($embedCode, 'telegram.me')) {
            return 'telegram';
        }

        if (str_contains($embedCode, 'twitter.com') || str_contains($embedCode, 'x.com')) {
            return 'twitter';
        }

        if (str_contains($embedCode, 'facebook.com') || str_contains($embedCode, 'fb.watch')) {
            return 'facebook';
        }

        if (str_contains($embedCode, 'vk.com') || str_contains($embedCode, 'vkvideo.ru')) {
            return 'vk';
        }

        if (str_contains($embedCode, 'ok.ru') || str_contains($embedCode, 'odnoklassniki.ru')) {
            return 'ok';
        }

        return null;
    }

    protected function isPlainUrl(string $value): bool
    {
        return preg_match('#^https?://\S+$#i', trim($value)) === 1;
    }

    protected function embedTypeRequiresHtmlCode(string $embedType): bool
    {
        return in_array($embedType, ['twitter', 'facebook', 'vk', 'ok', 'iframe'], true);
    }

    protected function wrapUrlAsAnchor(string $url): string
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<a href="' . $safeUrl . '">' . $safeUrl . '</a>';
    }

    protected function buildLegacyWpContentImageBlock(string $html, int $postId): ?array
    {
        if (!preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*\/?>/i', $html, $srcMatch)) {
            return null;
        }

        $imgSrc = html_entity_decode($srcMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $href = null;
        if (preg_match('/<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>/i', $html, $hrefMatch)) {
            $href = html_entity_decode($hrefMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $preferredPath = $this->normalizeLegacyWpContentPath($href);
        if ($preferredPath === null) {
            $preferredPath = $this->normalizeLegacyWpContentPath($imgSrc);
        }

        if ($preferredPath === null) {
            return null;
        }

        $alt = '';
        if (preg_match('/<img\b[^>]*\balt=["\']([^"\']*)["\'][^>]*\/?>/i', $html, $altMatch)) {
            $alt = trim(html_entity_decode($altMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $downloaded = $this->downloadLegacyWpContentImage($preferredPath, $postId);
        if ($downloaded === null) {
            return null;
        }

        return [
            'type' => 'images',
            'attributes' => [
                'images' => [[
                    'id'          => $downloaded['id'],
                    'link'        => $downloaded['link'],
                    'author'      => '',
                    'description' => $alt,
                ]],
            ],
        ];
    }

    protected function normalizeLegacyWpContentPath(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, '/wp-content/')) {
            return $url;
        }

        if (preg_match('#^https?://(?:www\.)?theins\.ru(/wp-content/[^?\#]+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function downloadLegacyWpContentImage(string $wpContentPath, int $postId): ?array
    {
        $path = $this->normalizeLegacyWpContentPath($wpContentPath);
        if ($path === null) {
            return null;
        }

        $filename = basename(parse_url($path, PHP_URL_PATH) ?? '');
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $imageId = 'wpimg_' . $postId . '_' . substr(md5($path), 0, 12);
        $targetPath = ImageService::getImagePath($imageId, ImageService::TYPE_CONTENT_IMAGE, ImageService::SIZE_ORIGINAL)
            . '/' . $filename;

        if (Storage::disk('public')->exists($targetPath)) {
            return ['id' => $imageId, 'link' => $targetPath];
        }

        $url = 'https://theins.ru' . $path;

        try {
            Storage::disk('public')->makeDirectory(dirname($targetPath));
            $fullPath = Storage::disk('public')->path($targetPath);

            $response = Http::timeout(30)
                ->withOptions(['sink' => $fullPath])
                ->get($url);

            if (!$response->successful()) {
                @unlink($fullPath);
                Log::warning("Не удалось загрузить legacy wp-content image for post #{$postId}: HTTP {$response->status()} ({$url})");
                return null;
            }

            return ['id' => $imageId, 'link' => $targetPath];
        } catch (\Exception $e) {
            Log::warning("Ошибка загрузки legacy wp-content image for post #{$postId}: " . $e->getMessage(), [
                'url' => $url,
            ]);
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Progress bar helpers
    // -------------------------------------------------------------------------

    /**
     * Create and start a pre-configured progress bar.
     * The bar overwrites itself in-place so it stays "sticky" at the bottom
     * of the terminal output while the rest scrolls above it.
     */
    protected function progressBar(int $total): \Symfony\Component\Console\Helper\ProgressBar
    {
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%%  %message%");
        $bar->setBarWidth(30);
        $bar->setMessage('');
        $bar->start();
        return $bar;
    }

    /**
     * Truncate a label to fit cleanly next to the progress bar.
     */
    protected function barLabel(string $text, int $maxLen = 55): string
    {
        return mb_strimwidth($text, 0, $maxLen, '…');
    }

    // -------------------------------------------------------------------------
    // Sequence reset
    // -------------------------------------------------------------------------

    /**
     * After bulk-inserting rows with explicit legacy IDs, PostgreSQL sequences
     * are NOT updated automatically. This method advances every sequence in the
     * public schema to MAX(id)+1 so that the next auto-generated insert never
     * collides with an already-occupied ID.
     */
    protected function resetSequences(): void
    {
        $this->info('Resetting PostgreSQL sequences...');

        $tables = DB::select("
            SELECT tablename
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ");

        $fixed = 0;

        foreach ($tables as $row) {
            $table = $row->tablename;

            try {
                $seqRow = DB::selectOne(
                    "SELECT pg_get_serial_sequence('{$table}', 'id') AS seq"
                );

                $seq = $seqRow->seq ?? null;
                if (!$seq) {
                    continue;
                }

                $maxId = DB::table($table)->max('id');
                if ($maxId === null) {
                    continue;
                }

                $nextVal    = (int) $maxId + 1;
                $currentVal = (int) (DB::selectOne("SELECT last_value FROM {$seq}")->last_value ?? 0);

                if ($currentVal < $nextVal) {
                    DB::statement("SELECT setval('{$seq}', {$nextVal})");
                    $this->line("  → {$table}: sequence {$currentVal} → {$nextVal}");
                    $fixed++;
                }
            } catch (\Throwable) {
                // Table has no integer PK sequence — skip silently.
            }
        }

        $this->info("Sequences reset: {$fixed} table(s) updated.");
    }
}
