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
            WebsiteSeeder::class,
            MerchantEmployeeSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Demo credentials');
        $this->command?->table(
            ['Account', 'Email', 'Password'],
            [
                ['Admin 1', 'admin@example.com', 'password'],
                ['Admin 2', 'entnasir23a@gmail.com', 'password'],
                ['Test User', 'user@example.com', 'password'],
            ]
        );
        $this->command?->comment('Plugin demo token (Test User / localhost): ' . DemoDataSeeder::DEMO_PLUGIN_TOKEN);
        $this->command?->comment('WordPress URL: ' . DemoDataSeeder::LOCAL_WORDPRESS_URL);
        $this->command?->comment('Plugin Origin header: ' . DemoDataSeeder::LOCAL_WORDPRESS_ORIGIN);
        $this->command?->comment('License domain (stored): ' . DemoDataSeeder::LOCAL_WORDPRESS_DOMAIN);
        $this->command?->comment('Demo employees seeded for Test User (see MerchantEmployeeSeeder).');
    }
}
