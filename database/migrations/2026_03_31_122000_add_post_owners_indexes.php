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
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_post_owners_user_post
             ON post_owners (user_id, post_id)'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_post_owners_post_user
             ON post_owners (post_id, user_id)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_post_owners_user_post');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_post_owners_post_user');
    }
};
