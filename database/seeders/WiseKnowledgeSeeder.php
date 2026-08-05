<?php

namespace Database\Seeders;

use App\WiseAi\Knowledge\Seed\PlatformKnowledgeSeeder;
use App\WiseAi\Language\RegionalKnowledgeSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeds Wise AI platform + regional knowledge scripts as drafts.
 *
 * Safe to re-run: upsert by external_id; never auto-publishes.
 * Human Publish remains in Wise AI → Knowledge (Seeded review).
 *
 * Usage:
 *   php artisan db:seed --class=WiseKnowledgeSeeder
 *   php artisan wise:seed-knowledge
 *   Admin → Database Migrations → Seed Wise knowledge
 */
class WiseKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $platform = app(PlatformKnowledgeSeeder::class)->run();
        $regional = app(RegionalKnowledgeSeeder::class)->run();

        $this->command?->info(sprintf(
            'Wise knowledge drafts: platform=%d (v%s), regional=%d across %s',
            $platform['upserted'],
            $platform['version'],
            $regional['upserted'],
            implode(', ', $regional['regions']),
        ));
        $this->command?->comment('Next: Wise AI → Knowledge → Seeded review → Publish.');
    }
}
