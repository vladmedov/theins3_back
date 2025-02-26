<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('translation_id')
                  ->nullable()
                  ->constrained('posts')
                  ->nullOnDelete(); 
        });
    }

    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['translation_id']);
            $table->dropColumn('translation_id');
        });
    }
};

