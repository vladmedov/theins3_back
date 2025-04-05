<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade'); // Если удаляем статью, удаляются и блоки
            $table->timestamps();
            $table->timestamp('published_at')->nullable(); // Используется только для типа online
            $table->enum('type', ['text', 'quote', 'gallery', 'image', 'video', 'iframe', 'embed']);
            $table->json('data'); // Данные блока (например, URL изображения или HTML-код embed)
            $table->integer('position'); // Позиция в публикации
        });
    }

    public function down() {
        Schema::dropIfExists('content_blocks');
    }
};
