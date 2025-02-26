<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('online_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->timestamp('published_at')->nullable();
            $table->enum('author_visibility', ['default', 'force_hidden', 'force_shown'])->default('default');
            $table->boolean('is_key_event')->default(false);
            $table->string('outline', 500)->nullable();
            $table->text('text')->nullable();
            $table->json('images')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_description')->nullable();
            $table->string('video_author')->nullable();
            $table->text('embed_code')->nullable();
            $table->enum('embed_type', ['hidden', 'telegram', 'twitter', 'facebook', 'instagram', 'vk', 'ok', 'iframe'])->default('hidden');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('online_messages');
    }
};
