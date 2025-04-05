<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Сначала очищаем данные
        DB::table('posts')->update(['columnist_id' => null]);

        // Затем меняем внешний ключ
        Schema::table('posts', function (Blueprint $table) {
            // Удаляем старый внешний ключ
            $table->dropForeign(['columnist_id']);
            
            // Добавляем новый внешний ключ
            $table->foreign('columnist_id')
                  ->references('id')
                  ->on('authors')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Удаляем новый внешний ключ
            $table->dropForeign(['columnist_id']);
            
            // Восстанавливаем старый внешний ключ
            $table->foreign('columnist_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }
}; 