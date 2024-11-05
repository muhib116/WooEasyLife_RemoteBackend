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

        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'entnasir23a@gmail.com',
        ]);
        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin@example.com',
        ]);
    }
}
