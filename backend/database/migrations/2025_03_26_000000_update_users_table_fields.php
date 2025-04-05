<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'slug',
                'ru_first_name',
                'en_first_name',
                'ru_second_name',
                'en_second_name',
                'ru_description',
                'en_description',
                'position',
                'twitter',
                'facebook',
                'hide_author_name_in_all_news',
                'hide_author_page',
                'hide_columnist_page',
                'is_authentication_disabled'
            ]);

            $table->string('name')->after('id')->nullable();
            $table->string('timezone')->nullable()->after('available_languages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->unique();
            $table->string('ru_first_name')->nullable();
            $table->string('en_first_name')->nullable();
            $table->string('ru_second_name')->nullable();
            $table->string('en_second_name')->nullable();
            $table->text('ru_description')->nullable();
            $table->text('en_description')->nullable();
            $table->string('position')->nullable();
            $table->string('twitter')->nullable();
            $table->string('facebook')->nullable();
            $table->boolean('hide_author_name_in_all_news')->default(false);
            $table->boolean('hide_author_page')->default(false);
            $table->boolean('hide_columnist_page')->default(false);
            $table->boolean('is_authentication_disabled')->default(false);

            $table->dropColumn(['name', 'timezone']);
        });
    }
}; 