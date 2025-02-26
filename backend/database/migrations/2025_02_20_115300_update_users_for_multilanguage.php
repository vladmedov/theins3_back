<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('users', function (Blueprint $table) {
            // Добавление новых полей
            $table->string('default_language')->default('ru')->after('email');
            $table->json('available_languages')->default(json_encode(['ru', 'en']))->after('default_language');
            
            // Добавление многоязычных полей
            $table->string('ru_first_name')->nullable()->after('slug');
            $table->string('en_first_name')->nullable()->after('ru_first_name');
            $table->string('ru_second_name')->nullable()->after('en_first_name');
            $table->string('en_second_name')->nullable()->after('ru_second_name');
            $table->text('ru_description')->nullable()->after('en_second_name');
            $table->text('en_description')->nullable()->after('ru_description');

            // Удаление старых полей
            $table->dropColumn(['first_name', 'second_name', 'description']);
        });
    }

    public function down() {
        Schema::table('users', function (Blueprint $table) {
            // Восстановление удаленных полей
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->text('description')->nullable();

            // Удаление добавленных полей
            $table->dropColumn([
                'default_language',
                'available_languages',
                'ru_first_name',
                'en_first_name',
                'ru_second_name',
                'en_second_name',
                'ru_description',
                'en_description',
            ]);
        });
    }
};
