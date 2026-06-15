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

        $seedUsers = [
            ['email' => 'admin@example.com', 'name' => 'Admin 1', 'role' => 'admin'],
            ['email' => 'entnasir23a@gmail.com', 'name' => 'Admin 2', 'role' => 'admin'],
            ['email' => 'user@example.com', 'name' => 'Test User', 'role' => 'user'],
        ];

        foreach ($seedUsers as $seedUser) {
            $user = User::withTrashed()->where('email', $seedUser['email'])->first();

            if ($user?->trashed()) {
                $user->restore();
            }

            User::updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'role' => $seedUser['role'],
                    'password' => $password,
                    'status' => true,
                ]
            );
        }
    }
}
