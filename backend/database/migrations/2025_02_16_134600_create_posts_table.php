<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->foreignId('category_id')->constrained();
            $table->foreignId('columnist_id')->nullable()->constrained('users');
            $table->foreignId('investigation_theme_id')->nullable()->constrained();

            $table->timestamps();
            $table->timestamp('published_at')->nullable();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();

            $table->enum('type', ['article', 'news', 'opinion', 'online']);
            $table->enum('status', ['draft', 'published']);

            $table->string('slug')->unique();
            $table->string('title');
            $table->text('lead')->nullable();
            $table->longText('content')->nullable();

            $table->integer('views_count')->default(0);
            $table->string('title_feature')->nullable();
        });
    }

    public function down() {
        Schema::dropIfExists('posts');
    }
};
