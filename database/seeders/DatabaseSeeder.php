<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        \App\Models\User::factory()->create([
            'name' => 'Admin 1',
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);
        \App\Models\User::factory()->create([
            'name' => 'Admin 2',
            'role' => 'admin',
            'email' => 'entnasir23a@gmail.com',
        ]);
        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'role' => 'user',
            'email' => 'user@example.com',
        ]);
    }
}
