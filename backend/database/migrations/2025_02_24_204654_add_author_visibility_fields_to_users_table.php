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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('hide_author_name_in_all_news')->default(false)->after('columnist_id');
            $table->boolean('hide_author_page')->default(false)->after('is_authors_force_shown');
            $table->boolean('hide_columnist_page')->default(false)->after('hide_author_page');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_authors_hidden',
                'is_authors_force_shown',
                'hide_author_page',
                'hide_columnist_page'
            ]);
        });
    }
};
