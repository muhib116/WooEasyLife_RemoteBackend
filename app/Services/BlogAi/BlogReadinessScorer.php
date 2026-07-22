<?php

namespace App\Services\BlogAi;

/**
 * Weighted readiness score (0–100) during / after auto generation.
 * Separate from post-publish engagement_score.
 */
class BlogReadinessScorer
{
    /**
     * @param  array{
     *     opportunity?: int|null,
     *     outline?: int|null,
     *     seo?: int|null,
     *     content?: int|null,
     *     image?: int|null,
     * }  $parts
     * @return array{score: int, breakdown: array<string, int|null>, weights: array<string, int>}
     */
    public function compute(array $parts): array
    {
        $weights = config('blog_ai.auto.weights', [
            'opportunity' => 15,
            'outline' => 15,
            'seo' => 30,
            'content' => 25,
            'image' => 15,
        ]);

        $activeWeight = 0;
        $accum = 0.0;
        $breakdown = [];

        foreach ($weights as $key => $weight) {
            $weight = (int) $weight;
            $value = array_key_exists($key, $parts) ? $parts[$key] : null;
            $breakdown[$key] = $value === null ? null : max(0, min(100, (int) $value));

            if ($breakdown[$key] === null) {
                continue;
            }

            $activeWeight += $weight;
            $accum += ($breakdown[$key] / 100) * $weight;
        }

        if ($activeWeight <= 0) {
            return [
                'score' => 0,
                'breakdown' => $breakdown,
                'weights' => $weights,
            ];
        }

        // Scale to full 100 based on known parts so early steps still show meaningful live scores.
        $score = (int) round(($accum / $activeWeight) * 100);

        return [
            'score' => max(0, min(100, $score)),
            'breakdown' => $breakdown,
            'weights' => $weights,
        ];
    }

    /**
     * Deterministic SEO part from BlogSeoQuality::analyze().
     *
     * @param  array<string, mixed>  $quality
     */
    public function scoreFromSeoQuality(array $quality): int
    {
        $checks = [
            'word_count_ok' => 14,
            'has_h2' => 6,
            'has_h3' => 4,
            'has_lists' => 4,
            'internal_links_ok' => 10,
            'keyword_in_title' => 10,
            'keyword_in_first_paragraph' => 10,
            'keyword_in_h2' => 8,
            'keyword_in_meta' => 6,
            'meta_description_ok' => 6,
            'faq_count_ok' => 8,
            'has_quick_answer' => 6,
            'has_ai_search_summary' => 4,
            'secondary_keyword_in_body' => 4,
            'has_content_image' => 4,
        ];

        $score = 0;
        foreach ($checks as $key => $points) {
            if (! empty($quality[$key])) {
                $score += $points;
            }
        }

        if (! empty($quality['slug_collision']) || ! empty($quality['focus_keyword_collision'])) {
            $score = min($score, 55);
        }

        // Soft informational signal — never required for ai_ready / publish.
        if (isset($quality['competitor_gap_coverage_pct']) && is_numeric($quality['competitor_gap_coverage_pct'])) {
            $pct = max(0, min(100, (int) $quality['competitor_gap_coverage_pct']));
            $score += (int) round(($pct / 100) * 6);
        }

        if (! empty($quality['ai_ready'])) {
            $score = max($score, 88);
        }

        return max(0, min(100, $score));
    }
}
