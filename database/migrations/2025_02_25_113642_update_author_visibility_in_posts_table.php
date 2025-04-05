<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            // Добавляем новое поле author_visibility
            $table->enum('author_visibility', ['default', 'force_hidden', 'force_shown'])
                  ->default('default')
                  ->after('id');

            // Удаляем старые поля, если они существуют
            $table->dropColumn('is_authors_hidden');
            $table->dropColumn('is_authors_force_shown');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            // Удаляем новое поле author_visibility
            $table->dropColumn('author_visibility');

            // Восстанавливаем старые поля
            $table->boolean('is_authors_hidden')->default(false);
            $table->boolean('is_authors_force_shown')->default(false);
        });
    }
};
