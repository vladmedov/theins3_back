<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('post_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_post_id')->constrained('posts')->onDelete('cascade');
            $table->integer('relation_weight')->default(0); // Вес связи
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('post_related');
    }
};
