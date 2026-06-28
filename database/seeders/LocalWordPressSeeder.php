<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds reference data plus the local WordPress merchant at http://localhost:8081/wordpress/.
 *
 * Usage:
 *   php artisan db:seed --class=LocalWordPressSeeder
 */
class LocalWordPressSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RequiredTableSeeder::class,
            DemoDataSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Local WordPress (' . DemoDataSeeder::LOCAL_WORDPRESS_URL . ') seeded.');
        $this->command?->table(
            ['Account', 'Email', 'Password'],
            [
                ['Admin 1', 'admin@example.com', 'password'],
                ['Admin 2', 'entnasir23a@gmail.com', 'password'],
                ['Test User', 'user@example.com', 'password'],
            ]
        );
        $this->command?->comment('Plugin demo token: ' . DemoDataSeeder::DEMO_PLUGIN_TOKEN);
        $this->command?->comment('WordPress URL: ' . DemoDataSeeder::LOCAL_WORDPRESS_URL);
        $this->command?->comment('Plugin Origin header: ' . DemoDataSeeder::LOCAL_WORDPRESS_ORIGIN);
        $this->command?->comment('License domain (stored): ' . DemoDataSeeder::LOCAL_WORDPRESS_DOMAIN);
    }
}
