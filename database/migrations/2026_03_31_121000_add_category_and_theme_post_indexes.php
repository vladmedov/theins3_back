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
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_posts_status_category_published_at_id
             ON posts (status, category_id, published_at DESC, id DESC)'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_posts_status_investigation_theme_published_at_id
             ON posts (status, investigation_theme_id, published_at DESC, id DESC)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_posts_status_category_published_at_id');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_posts_status_investigation_theme_published_at_id');
    }
};
