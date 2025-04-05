<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('slug')->unique()->after('id');
            $table->string('first_name')->nullable()->after('email');
            $table->string('second_name')->nullable()->after('first_name');
            $table->json('roles')->default(json_encode(['user']))->after('password');
            $table->string('position')->nullable()->after('roles');
            $table->text('description')->nullable()->after('position');
            $table->string('twitter')->nullable()->after('description');
            $table->string('facebook')->nullable()->after('twitter');
            $table->string('avatar')->nullable()->after('facebook');
        });
    }

    public function down() {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->dropColumn([
                'slug', 'first_name', 'second_name', 'roles', 'position',
                'description', 'twitter', 'facebook', 'avatar'
            ]);
        });
    }
};

