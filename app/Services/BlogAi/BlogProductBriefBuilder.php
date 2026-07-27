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
        private BlogLandingContextService $landingContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $cluster = null): array
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
            'voice_do' => array_values(config('blog_ai.persona.voice_do', [])),
            'voice_dont' => array_values(config('blog_ai.persona.voice_dont', [])),
            'founder' => config('blog_ai.persona.founder'),
            'author_name' => config('blog_ai.author_name'),
            'location' => config('landing.location', 'ঢাকা, বাংলাদেশ'),
            'footer_tagline' => config('landing.footer_tagline'),
            'product_truth' => config('blog_ai.product_truth', []),
            'preferred_feature_themes' => array_values(config('blog_ai.preferred_feature_themes', [])),
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
                'Obey product_truth.hero_claims and product_truth.do_not_claim strictly (FEATURES.md sync).',
                'SteadFast Return Requests / Notifications / stuck scan are Shipped; Pathao/RedX do NOT have that hub.',
                'Messenger human inbox is Shipped; soft-mention AI Semi only; never Meta AI Bot or unlocked AI Full for all.',
                'Target Bangladesh WooCommerce / COD sellers only.',
                'Write primarily in Bangla (bn). Use a Latin SEO slug.',
                'Soft-promote WooEasyLife; prioritize helpful education over hard sell.',
                'Obey performance_learning guidance when choosing topic angle and hooks.',
                'Stay aligned with cluster_landing / landing_page_reference (primary_url, H1, lead, FAQs, claims). Do not contradict landing SEO copy.',
                'Treat landing_page_reference.primary_url as the content source of truth; use editorial skeleton for blog section flow (do not clone landing layout).',
                'Include a soft CTA to the cluster primary_path (and /pricing when natural).',
                'Rank SEO tools: every post MUST internally link cluster must_link_paths first, using high-intent keyword anchors from seo_tools (never generic “এখানে ক্লিক”).',
                'When natural, also link 1 related free tool (return-loss / courier-charge / ads-roas / fraud checker) so tool pages gain topical authority.',
                'Courier / SteadFast posts should soft-link /steadfast-integration for return/stuck intents.',
                'Focus keyword should match search intent for the primary tool when the cluster is tool-led (fraud_checker, return_loss, courier_charge, facebook_ads).',
                'Voice: Messenger-style Bangla seller talk. Prefer short paragraphs over listicles. Ban corporate/AI fluff (see voice_dont).',
                'Never open sections with “আজকের ডিজিটাল যুগে”, “গুরুত্বপূর্ণ বিষয় হলো”, or English “In today’s digital age”.',
                'Do not start every section with the focus keyword; place it naturally once in first paragraph + one H2.',
                'Prefer preferred_feature_themes when picking cold-start topics (Courier hub / Messenger launches).',
            ],
        ];

        if (filled($cluster)) {
            $landing = $this->landingContext->forCluster((string) $cluster);
            $brief['cluster'] = (string) $cluster;
            $brief['cluster_label'] = app(BlogClusterCatalog::class)->label((string) $cluster);
            $brief['cluster_landing'] = $landing;
            $brief['seo_tools'] = $this->toolsForCluster((string) $cluster, $landing);
        } else {
            $brief['landing_page_catalog'] = $this->landingContext->catalog();
            $brief['seo_tools'] = config('blog_ai.seo_tools', []);
        }

        if (config('blog_ai.analytics.learning_in_prompts', true)) {
            $brief['performance_learning'] = $this->learningService->promptLearningBlock();
        }

        if (config('blog_ai.memory.enabled', true) && config('blog_ai.memory.in_prompts', true)) {
            $brief['standing_memory'] = app(BlogMemoryService::class)->promptBlock($cluster);
            $brief['rules'][] = 'Obey standing_memory (prefer/avoid keywords, instructions, brand notes, lessons).';
        }

        return $brief;
    }

    public function toPromptBlock(?string $cluster = null): string
    {
        return json_encode($this->build($cluster), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $landing
     * @return list<array<string, mixed>>
     */
    private function toolsForCluster(string $cluster, array $landing): array
    {
        $tools = collect(config('blog_ai.seo_tools', []))
            ->filter(fn ($row) => is_array($row) && filled($row['path'] ?? null))
            ->values();

        $priorityPaths = array_values(array_filter([
            ...(is_array($landing['must_link_paths'] ?? null) ? $landing['must_link_paths'] : []),
            $landing['primary_path'] ?? null,
            ...(is_array($landing['related_paths'] ?? null) ? $landing['related_paths'] : []),
        ]));

        return $tools
            ->sortBy(function (array $tool) use ($priorityPaths) {
                $path = (string) $tool['path'];
                $idx = array_search($path, $priorityPaths, true);

                return $idx === false
                    ? 1000 - (int) ($tool['priority'] ?? 0)
                    : $idx;
            })
            ->values()
            ->take(6)
            ->all();
    }
}
