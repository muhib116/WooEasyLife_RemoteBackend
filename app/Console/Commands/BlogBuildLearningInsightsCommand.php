<?php

namespace App\Console\Commands;

use App\Services\BlogAi\BlogLearningService;
use Illuminate\Console\Command;

class BlogBuildLearningInsightsCommand extends Command
{
    protected $signature = 'blog:build-learning-insights {--rollup-only : Only roll up analytics, skip insight snapshot}';

    protected $description = 'Roll up blog engagement analytics and build AI learning insights';

    public function handle(BlogLearningService $learning): int
    {
        if ($this->option('rollup-only')) {
            $rollup = $learning->rollupAnalytics();
            $this->info("Rolled up {$rollup['slugs']} slugs ({$rollup['events']} event-linked counts).");
            $gsc = $learning->syncGscPageMetrics();
            if (! empty($gsc['skipped'])) {
                $this->line('GSC page sync skipped (no SEO_GSC_* credentials).');
            } elseif (! empty($gsc['error'])) {
                $this->warn('GSC sync error: '.$gsc['error']);
            } else {
                $this->info('GSC pages synced: '.($gsc['synced'] ?? 0));
            }

            return self::SUCCESS;
        }

        $insight = $learning->buildInsights();
        $this->info('Learning insight #'.$insight->id.' saved at '.$insight->generated_at);
        if ($insight->summary_bn) {
            $this->line($insight->summary_bn);
        }

        return self::SUCCESS;
    }
}
