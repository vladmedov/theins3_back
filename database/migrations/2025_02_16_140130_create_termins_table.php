<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('termins', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5); // Язык термина (ru, en и т. д.)
            $table->string('termin')->unique(); // Сам термин
            $table->text('description')->nullable(); // Описание термина
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('termins');
    }
};
