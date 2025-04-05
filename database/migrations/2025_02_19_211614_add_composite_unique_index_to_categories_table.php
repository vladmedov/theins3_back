<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['language_code', 'slug']);
        });
    }

    public function down() {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['language_code', 'slug']);
            $table->unique(['slug']);
        });
    }
};