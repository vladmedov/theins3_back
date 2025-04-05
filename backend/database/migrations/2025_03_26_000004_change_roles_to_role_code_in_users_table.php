<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Удаляем старое поле roles
            $table->dropColumn('roles');
            
            // Добавляем новое поле role_code
            $table->string('role_code')->default('user')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Удаляем новое поле role_code
            $table->dropColumn('role_code');
            
            // Восстанавливаем старое поле roles
            $table->json('roles')->default(json_encode(['user']))->after('password');
        });
    }
}; 