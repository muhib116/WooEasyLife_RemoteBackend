<?php

use App\Models\BlogCluster;
use App\Services\BlogAi\BlogClusterCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Sync Courier hub + Messenger product truth into blog_clusters from config/blog_ai.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_clusters')) {
            return;
        }

        $labels = config('blog_ai.clusters', []);
        $landings = config('blog_ai.cluster_landing', []);
        $seeds = config('blog_ai.cluster_seed_queries', []);
        $needles = config('blog_ai.cluster_detect_needles', []);

        foreach (['courier', 'messenger'] as $i => $key) {
            if (! is_array($labels) || ! isset($labels[$key])) {
                continue;
            }

            $payload = [
                'label' => (string) $labels[$key],
                'seed_queries' => is_array($seeds[$key] ?? null) ? array_values($seeds[$key]) : [],
                'landing_json' => is_array($landings[$key] ?? null) ? $landings[$key] : [],
                'detect_needles' => is_array($needles[$key] ?? null) ? array_values($needles[$key]) : [],
                'sort_order' => $key === 'messenger' ? 95 : 50,
                'is_active' => true,
            ];

            BlogCluster::query()->updateOrCreate(['key' => $key], $payload);
        }

        app(BlogClusterCatalog::class)->forgetCache();
    }

    public function down(): void
    {
        // Keep messenger row — product feature remains; no destructive rollback.
    }
};
