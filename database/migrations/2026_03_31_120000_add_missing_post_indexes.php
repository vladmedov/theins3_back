<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_posts_language_status_published_at_id
             ON posts (language_code, status, published_at DESC, id DESC)'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_posts_slug_language_status_not_null
             ON posts (slug, language_code, status)
             WHERE slug IS NOT NULL'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_post_authors_post_position_author
             ON post_authors (post_id, position, author_id)'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_post_authors_author_post
             ON post_authors (author_id, post_id)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_posts_language_status_published_at_id');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_posts_slug_language_status_not_null');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_post_authors_post_position_author');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_post_authors_author_post');
    }
};
