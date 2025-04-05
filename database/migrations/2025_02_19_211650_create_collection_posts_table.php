<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('collection_post', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->string('collection_code', 16);
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['language_code', 'collection_code', 'post_id']);
        });
    }

    public function down() {
        Schema::dropIfExists('collection_post');
    }
};