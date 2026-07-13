<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Добавление поля is_super_author.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_super_author')->default(false)->after('is_super_news');
        });

        DB::table('posts')
            ->where('type', 'opinion')
            ->update(['is_super_author' => true]);
    }

    /**
     * Откат изменений.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('is_super_author');
        });
    }
};
