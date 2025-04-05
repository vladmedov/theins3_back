<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->string('slug')->unique();
            $table->string('title');
            $table->timestamps();
        });

        // Создаём таблицу для связи Post ↔ Tags (многие-ко-многим)
        Schema::create('post_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('post_tags');
        Schema::dropIfExists('tags');
    }
};
