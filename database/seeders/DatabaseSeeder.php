<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin 1', 'role' => 'admin', 'password' => $password, 'status' => true]
        );

        User::updateOrCreate(
            ['email' => 'entnasir23a@gmail.com'],
            ['name' => 'Admin 2', 'role' => 'admin', 'password' => $password, 'status' => true]
        );

        User::updateOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Test User', 'role' => 'user', 'password' => $password, 'status' => true]
        );
    }
}
