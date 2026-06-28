<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RequiredTableSeeder::class,
            DemoDataSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Demo credentials');
        $this->command?->table(
            ['Account', 'Email', 'Password'],
            [
                ['Super Admin', 'admin@example.com', 'password'],
                ['Billing Clerk Admin', 'entnasir23a@gmail.com', 'password'],
                ['Demo Merchant', 'user@example.com', 'password'],
                ['Demo Merchant 2', 'merchant2@example.com', 'password'],
                ['Shop Manager (portal staff)', 'manager@localhost', 'password'],
            ]
        );
        $this->command?->comment('Plugin demo token (Test User / localhost): ' . DemoDataSeeder::DEMO_PLUGIN_TOKEN);
        $this->command?->comment('WordPress URL: ' . DemoDataSeeder::LOCAL_WORDPRESS_URL);
        $this->command?->comment('Plugin Origin header: ' . DemoDataSeeder::LOCAL_WORDPRESS_ORIGIN);
        $this->command?->comment('License domain (stored): ' . DemoDataSeeder::LOCAL_WORDPRESS_DOMAIN);
    }
}
