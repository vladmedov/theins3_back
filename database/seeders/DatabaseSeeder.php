<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'email' => 'admin@mteam.org',
            'password' => bcrypt('s39AS3fk9@3sh3Nsa.d3q9mc'),
            'name' => 'Admin MTeam',
            'avatar' => null,
            'role_code' => 'admin',
            'available_languages' => ["ru" => true,"en" => true],
            'timezone' => 'Europe/Moscow',
        ]);
    }
}
