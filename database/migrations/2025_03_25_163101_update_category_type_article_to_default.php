<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up() {
        // Добавляем новое поле
        DB::statement("ALTER TABLE categories ADD COLUMN new_type VARCHAR(255)");
        
        // Копируем данные с нужными изменениями
        DB::statement("
            UPDATE categories 
            SET new_type = CASE 
                WHEN type = 'article' THEN 'default'
                WHEN type = 'online' THEN 'default'
                ELSE type 
            END
        ");
        
        // Удаляем старое поле и переименовываем новое
        DB::statement("ALTER TABLE categories DROP COLUMN type");
        DB::statement("ALTER TABLE categories RENAME COLUMN new_type TO type");
        
        // Добавляем ограничение
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_type_check CHECK (type IN ('default', 'news', 'opinion'))");
    }

    public function down() {
        // Добавляем новое поле
        DB::statement("ALTER TABLE categories ADD COLUMN old_type VARCHAR(255)");
        
        // Копируем данные обратно
        DB::statement("
            UPDATE categories 
            SET old_type = CASE 
                WHEN type = 'default' AND title IN ('Онлайн', 'Online') THEN 'online'
                WHEN type = 'default' THEN 'article'
                ELSE type 
            END
        ");
        
        // Удаляем новое поле и переименовываем старое
        DB::statement("ALTER TABLE categories DROP COLUMN type");
        DB::statement("ALTER TABLE categories RENAME COLUMN old_type TO type");
        
        // Добавляем старое ограничение
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_type_check CHECK (type IN ('article', 'news', 'opinion', 'online'))");
    }
}; 