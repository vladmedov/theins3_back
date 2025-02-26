<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->string('slug')->unique();
            $table->string('title');
            $table->integer('position')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->json('widgets')->nullable(); // JSON-объект с настройками виджетов
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('categories');
    }
};

