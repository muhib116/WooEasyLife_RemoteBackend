<?php

namespace App\Console\Commands;

use App\Services\BlogAi\BlogLearningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BlogBuildLearningInsightsCommand extends Command
{
    protected $signature = 'blog:build-learning-insights {--rollup-only : Only roll up analytics, skip insight snapshot}';

    protected $description = 'Roll up blog engagement analytics and build AI learning insights';

    public function handle(BlogLearningService $learning): int
    {
        $required = ['blog_content_events', 'blog_post_analytics', 'blog_learning_insights'];
        $missing = array_values(array_filter($required, fn (string $table) => ! Schema::hasTable($table)));

        if ($missing !== []) {
            $this->error(
                'Missing tables: '.implode(', ', $missing)
                .'. Run migrations first (Database Migrations or `php artisan migrate --force`).'
            );

            return self::FAILURE;
        }

        try {
            if ($this->option('rollup-only')) {
                $rollup = $learning->rollupAnalytics();
                $this->info("Rolled up {$rollup['slugs']} slugs ({$rollup['events']} event-linked counts).");
                $gsc = $learning->syncGscPageMetrics();
                if (! empty($gsc['skipped'])) {
                    $this->line('GSC page sync skipped (set SEO_GSC_SITE_URL + OAuth / token).');
                } elseif (! empty($gsc['error'])) {
                    $this->warn('GSC sync error: '.$gsc['error']);
                } else {
                    $this->info('GSC pages synced: '.($gsc['synced'] ?? 0));
                }

                $gscQueries = $learning->syncGscQueryMetrics();
                if (! empty($gscQueries['skipped']) && ($gscQueries['error'] ?? null) === 'missing_table') {
                    $this->warn('GSC query sync skipped (run migrations for blog_gsc_query_metrics).');
                } elseif (! empty($gscQueries['skipped'])) {
                    $this->line('GSC query sync skipped (set SEO_GSC_SITE_URL + OAuth / token).');
                } elseif (! empty($gscQueries['error'])) {
                    $this->warn('GSC query sync error: '.$gscQueries['error']);
                } else {
                    $this->info('GSC query×page rows synced: '.($gscQueries['synced'] ?? 0));
                }

                return self::SUCCESS;
            }

            $insight = $learning->buildInsights();
            $this->info('Learning insight #'.$insight->id.' saved at '.$insight->generated_at);
            if ($insight->summary_bn) {
                $this->line($insight->summary_bn);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Learning insights failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
