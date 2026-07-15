<?php

namespace App\Services\BlogAi;

use App\Services\LandingSettingsService;

/**
 * Compact product truth for BD SEO blog generation (config-first, no heavy plan queries).
 */
class BlogProductBriefBuilder
{
    public function __construct(
        private LandingSettingsService $landingSettings,
        private BlogLearningService $learningService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $hero = config('landing.hero', []);
        $bullets = config('landing.hero_bullets', []);
        $roi = config('landing.roi_scenarios', []);
        $howItWorks = config('landing.how_it_works', []);
        $announcements = config('landing.announcement.messages', []);

        $roiLines = collect(is_array($roi) ? $roi : [])
            ->take(4)
            ->map(fn ($row) => trim(($row['title'] ?? '').': '.($row['benefit'] ?? $row['calculation'] ?? '')))
            ->filter()
            ->values()
            ->all();

        $featureOrder = config('landing.feature_highlight_order', []);

        $brief = [
            'market' => config('blog_ai.market'),
            'product' => config('blog_ai.persona.product'),
            'audience' => config('blog_ai.persona.audience'),
            'tone' => config('blog_ai.persona.tone'),
            'founder' => config('blog_ai.persona.founder'),
            'author_name' => config('blog_ai.author_name'),
            'location' => config('landing.location', 'ঢাকা, বাংলাদেশ'),
            'footer_tagline' => config('landing.footer_tagline'),
            'hero' => [
                'badge' => $hero['badge'] ?? null,
                'headline' => $hero['headline'] ?? null,
                'headline_accent' => $hero['headline_accent'] ?? null,
                'subheadline' => $hero['subheadline'] ?? null,
            ],
            'hero_bullets' => array_values(is_array($bullets) ? $bullets : []),
            'feature_keys' => array_values(array_slice(is_array($featureOrder) ? $featureOrder : [], 0, 12)),
            'how_it_works' => collect(is_array($howItWorks) ? $howItWorks : [])->take(6)->values()->all(),
            'roi_scenarios' => $roiLines,
            'announcement_messages' => is_array($announcements) ? $announcements : [],
            'trial_hint' => '১৪ দিন ফ্রি ট্রায়াল (landing trust badges)',
            'support' => [
                'whatsapp_configured' => filled($this->landingSettings->adminWhatsapp()),
            ],
            'rules' => [
                'Do not invent prices, merchant counts, courier brand partnerships, or guarantees not listed here.',
                'Target Bangladesh WooCommerce / COD sellers only.',
                'Write primarily in Bangla (bn). Use a Latin SEO slug.',
                'Soft-promote WooEasyLife; prioritize helpful education over hard sell.',
                'Obey performance_learning guidance when choosing topic angle and hooks.',
            ],
        ];

        if (config('blog_ai.analytics.learning_in_prompts', true)) {
            $brief['performance_learning'] = $this->learningService->promptLearningBlock();
        }

        return $brief;
    }

    public function toPromptBlock(): string
    {
        return json_encode($this->build(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }
}
