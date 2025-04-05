<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('investigation_themes', function (Blueprint $table) {
            $table->boolean('is_main')->default(false)->after('name');
        });
    }

    public function down() {
        Schema::table('investigation_themes', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
