<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('is_hidden', 'is_show_in_menu');
        });
        
        DB::statement('UPDATE categories SET is_show_in_menu = NOT is_show_in_menu');
    }

    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('is_show_in_menu', 'is_hidden');
        });
        
        DB::statement('UPDATE categories SET is_hidden = NOT is_hidden');
    }
};