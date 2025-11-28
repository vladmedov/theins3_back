<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->unique()->comment('Type of entity being synced: posts, categories, authors, etc.');
            $table->timestamp('last_synced_at')->nullable()->comment('Timestamp of the last successful sync');
            $table->enum('status', ['idle', 'running', 'completed', 'failed'])->default('idle')->comment('Current status of the sync process');
            $table->text('error_message')->nullable()->comment('Error message if sync failed');
            $table->timestamps();
            
            $table->index(['entity_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
