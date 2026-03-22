<?php

namespace Database\Seeders;

use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Тестовый администратор для локальной разработки / db:seed.
     *
     * Создание выполняется только если в .env заданы SEED_ADMIN_EMAIL и SEED_ADMIN_PASSWORD.
     *
     * Пароль задаётся открытым текстом: в User casts указано 'password' => 'hashed',
     * поэтому хеширование выполняется автоматически при сохранении (не используйте bcrypt() здесь).
     */
    public function run(): void
    {
        $emailRaw = env('SEED_ADMIN_EMAIL');
        $plainPassword = env('SEED_ADMIN_PASSWORD');

        $email = is_string($emailRaw) ? trim($emailRaw) : '';

        if ($email === '' || ! is_string($plainPassword) || $plainPassword === '') {
            if ($this->command) {
                $this->command->warn('AdminUserSeeder: пропуск — задайте SEED_ADMIN_EMAIL и SEED_ADMIN_PASSWORD в .env');
            }

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SEED_ADMIN_NAME', 'Test Admin'),
                'password' => $plainPassword,
                'role_code' => UserRoles::ADMIN,
                'available_languages' => ['ru' => true, 'en' => true],
                'timezone' => env('SEED_ADMIN_TIMEZONE', 'Europe/Moscow'),
            ]
        );

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }
}
