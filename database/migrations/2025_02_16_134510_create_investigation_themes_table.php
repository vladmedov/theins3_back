<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('investigation_themes', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->string('cover_image')->nullable(); // Обложка темы
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('investigation_themes');
    }
};
