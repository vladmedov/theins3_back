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
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->string('slug');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('avatar')->nullable();
            $table->string('position')->nullable();
            $table->text('description')->nullable();
            $table->string('twitter')->nullable();
            $table->string('facebook')->nullable();
            $table->json('allowed_post_types')->nullable();
            $table->json('post_types_with_hidden_author_name')->nullable();
            $table->boolean('is_author_page_hidden')->default(false);
            $table->boolean('is_columnist_page_hidden')->default(false);
            $table->timestamps();

            $table->index('language_code');
            $table->unique(['language_code', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
}; 