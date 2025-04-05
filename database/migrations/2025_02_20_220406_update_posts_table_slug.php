<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('tags', function (Blueprint $table) {
            $table->unique(['language_code', 'slug']);
        });
    }

    public function down() {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['language_code', 'slug']);
            $table->string('slug')->unique()->change();
        });
    }
};
