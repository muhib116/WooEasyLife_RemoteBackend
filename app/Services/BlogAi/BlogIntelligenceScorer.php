<?php

namespace App\Services\BlogAi;

use App\Models\BlogCompetitorAnalysis;
use App\Models\BlogContentEvent;
use App\Models\BlogGscQueryMetric;
use App\Models\BlogLearningInsight;
use App\Services\LandingSettingsService;
use App\Services\Seo\GoogleSearchConsoleClient;
use Illuminate\Support\Facades\Schema;

/**
 * 0–100 “system intelligence” score for the blog AI loop (not model training).
 */
class BlogIntelligenceScorer
{
    public function __construct(
        private LandingSettingsService $landingSettings,
        private GoogleSearchConsoleClient $gsc,
    ) {}

    /**
     * @return array{
     *     score: int,
     *     label: string,
     *     label_bn: string,
     *     color: string,
     *     dimensions: list<array{key: string, label: string, score: int, max: int, status: string, hint: string}>,
     *     next_steps: list<string>
     * }
     */
    public function score(): array
    {
        $dimensions = [
            $this->scoreAiWriter(),
            $this->scoreAnalytics(),
            $this->scoreGsc(),
            $this->scoreLearning(),
            $this->scoreMemory(),
            $this->scoreCompetitors(),
        ];

        $total = (int) collect($dimensions)->sum('score');
        $max = (int) collect($dimensions)->sum('max');
        $pct = $max > 0 ? (int) round(($total / $max) * 100) : 0;
        $pct = max(0, min(100, $pct));

        $meta = $this->labelFor($pct);
        $nextSteps = collect($dimensions)
            ->filter(fn (array $d) => $d['score'] < $d['max'])
            ->sortBy(fn (array $d) => $d['score'] / max(1, $d['max']))
            ->take(4)
            ->pluck('hint')
            ->filter()
            ->values()
            ->all();

        return [
            'score' => $pct,
            'label' => $meta['label'],
            'label_bn' => $meta['label_bn'],
            'color' => $meta['color'],
            'dimensions' => $dimensions,
            'next_steps' => $nextSteps,
        ];
    }

    /**
     * @return array{key: string, label: string, score: int, max: int, status: string, hint: string}
     */
    private function scoreAiWriter(): array
    {
        $max = 15;
        $score = 0;
        $enabled = (bool) config('blog_ai.enabled', true);
        $hasKey = filled($this->landingSettings->openaiApiKey());

        if ($enabled) {
            $score += 7;
        }
        if ($hasKey) {
            $score += 8;
        }

        $status = $score >= $max ? 'ready' : ($score > 0 ? 'partial' : 'missing');
        $hint = ! $hasKey
            ? 'Set OpenAI API key in Landing Settings.'
            : (! $enabled ? 'Enable Blog AI (BLOG_AI_ENABLED).' : 'AI writer ready.');

        return [
            'key' => 'ai_writer',
            'label' => 'AI writer',
            'score' => $score,
            'max' => $max,
            'status' => $status,
            'hint' => $hint,
        ];
    }

    /**
     * @return array{key: string, label: string, score: int, max: int, status: string, hint: string}
     */
    private function scoreAnalytics(): array
    {
        $max = 15;
        $score = 0;
        $enabled = (bool) config('blog_ai.analytics.enabled', true);
        $events = 0;
        if (Schema::hasTable('blog_content_events')) {
            $events = BlogContentEvent::query()
                ->where('created_at', '>=', now()->subDays(28))
                ->count();
        }

        if ($enabled) {
            $score += 5;
        }
        if ($events > 0) {
            $score += 5;
        }
        if ($events >= 50) {
            $score += 5;
        }

        $status = $score >= $max ? 'ready' : ($score > 0 ? 'partial' : 'missing');
        $hint = $events === 0
            ? 'Publish posts and let /blog traffic record engagement events.'
            : 'First-party analytics collecting.';

        return [
            'key' => 'analytics',
            'label' => 'Engagement analytics',
            'score' => $score,
            'max' => $max,
            'status' => $status,
            'hint' => $hint,
        ];
    }

    /**
     * @return array{key: string, label: string, score: int, max: int, status: string, hint: string}
     */
    private function scoreGsc(): array
    {
        $max = 25;
        $score = 0;
        $statusPayload = $this->gsc->configurationStatus();
        $ready = (bool) ($statusPayload['ready'] ?? false);
        $metrics = 0;
        if (Schema::hasTable('blog_gsc_query_metrics')) {
            $metrics = BlogGscQueryMetric::query()->count();
        }

        if (filled($this->gsc->siteUrl())) {
            $score += 5;
        }
        if ($ready) {
            $score += 10;
        }
        if ($metrics > 0) {
            $score += 5;
        }
        if ($metrics >= 20) {
            $score += 5;
        }

        $status = $score >= $max ? 'ready' : ($score > 0 ? 'partial' : 'missing');
        $hint = ! $ready
            ? 'Connect Search Console (Admin → SEO & Learning) — free real keyword demand.'
            : ($metrics === 0
                ? 'Run Blog learning insights to sync GSC queries for Smart Post.'
                : 'Search Console opportunities drive Smart Post topic picks.');

        return [
            'key' => 'gsc',
            'label' => 'Search Console',
            'score' => $score,
            'max' => $max,
            'status' => $status,
            'hint' => $hint,
        ];
    }

    /**
     * @return array{key: string, label: string, score: int, max: int, status: string, hint: string}
     */
    private function scoreLearning(): array
    {
        $max = 20;
        $score = 0;
        $insight = Schema::hasTable('blog_learning_insights')
            ? BlogLearningInsight::latestGlobal()
            : null;

        if ($insight) {
            $score += 10;
            if ($insight->generated_at && $insight->generated_at->gte(now()->subDays(2))) {
                $score += 10;
            } elseif ($insight->generated_at && $insight->generated_at->gte(now()->subDays(7))) {
                $score += 5;
            }
        }

        $status = $score >= $max ? 'ready' : ($score > 0 ? 'partial' : 'missing');
        $hint = ! $insight
            ? 'Run Blog learning insights to build a performance snapshot.'
            : ($score < $max
                ? 'Refresh learning insights (daily cron or Maintenance).'
                : 'Learning snapshot is fresh and injected into prompts.');

        return [
            'key' => 'learning',
            'label' => 'Self-learning',
            'score' => $score,
            'max' => $max,
            'status' => $status,
            'hint' => $hint,
        ];
    }

    /**
     * @return array{key: string, label: string, score: int, max: int, status: string, hint: string}
     */
    private function scoreMemory(): array
    {
        $max = 15;
        $score = 0;
        $active = 0;
        $manual = 0;
        if (Schema::hasTable('blog_ai_memories')) {
            $active = \App\Models\BlogAiMemory::query()->where('is_active', true)->count();
            $manual = \App\Models\BlogAiMemory::query()
                ->where('is_active', true)
                ->where('source', \App\Models\BlogAiMemory::SOURCE_MANUAL)
                ->count();
        }

        if ($active >= 1) {
            $score += 5;
        }
        if ($active >= 8) {
            $score += 5;
        }
        if ($manual >= 1) {
            $score += 3;
        }
        if ($active >= 20) {
            $score += 2;
        }

        $status = $score >= $max ? 'ready' : ($score > 0 ? 'partial' : 'missing');
        $hint = $active === 0
            ? 'Add standing memory: prefer/avoid keywords, topics, and writing instructions.'
            : ($score < $max
                ? 'Grow memory daily — add instructions and let learning absorb GSC wins.'
                : 'Standing memory is compounding into every draft.');

        return [
            'key' => 'memory',
            'label' => 'Standing memory',
            'score' => $score,
            'max' => $max,
            'status' => $status,
            'hint' => $hint,
        ];
    }

    /**
     * @return array{key: string, label: string, score: int, max: int, status: string, hint: string}
     */
    private function scoreCompetitors(): array
    {
        $max = 25;
        $score = 0;
        $count = 0;
        $recent = 0;
        if (Schema::hasTable('blog_competitor_analyses')) {
            $count = BlogCompetitorAnalysis::query()->count();
            $recent = BlogCompetitorAnalysis::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
        }

        if ($count >= 1) {
            $score += 10;
        }
        if ($count >= 3) {
            $score += 5;
        }
        if ($recent >= 1) {
            $score += 5;
        }
        if ($recent >= 3) {
            $score += 5;
        }

        $status = $score >= $max ? 'ready' : ($score > 0 ? 'partial' : 'missing');
        $hint = $count === 0
            ? 'Analyze 1–5 competitor URLs for a target keyword.'
            : ($score < $max
                ? 'Run more competitor analyses on striking-distance keywords.'
                : 'Competitor gaps are available for smarter drafts.');

        return [
            'key' => 'competitors',
            'label' => 'Competitor analyzer',
            'score' => $score,
            'max' => $max,
            'status' => $status,
            'hint' => $hint,
        ];
    }

    /**
     * @return array{label: string, label_bn: string, color: string}
     */
    private function labelFor(int $score): array
    {
        return match (true) {
            $score >= 85 => [
                'label' => 'Elite',
                'label_bn' => 'এলিট ইন্টেলিজেন্স',
                'color' => 'emerald',
            ],
            $score >= 70 => [
                'label' => 'Sharp',
                'label_bn' => 'শার্প ইন্টেলিজেন্স',
                'color' => 'sky',
            ],
            $score >= 45 => [
                'label' => 'Warming',
                'label_bn' => 'উষ্ণ হচ্ছে',
                'color' => 'amber',
            ],
            $score >= 20 => [
                'label' => 'Cold start',
                'label_bn' => 'কোল্ড স্টার্ট',
                'color' => 'orange',
            ],
            default => [
                'label' => 'Offline',
                'label_bn' => 'অফলাইন',
                'color' => 'rose',
            ],
        };
    }
}
