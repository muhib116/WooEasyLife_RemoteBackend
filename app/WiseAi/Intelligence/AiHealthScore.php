<?php

namespace App\WiseAi\Intelligence;

use App\Models\WiseAi\WiseExperienceSignal;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Language\LlmLanguageConfig;
use Illuminate\Support\Facades\DB;

/**
 * Composite 0–100 AI health for live Dashboard monitoring.
 */
class AiHealthScore
{
    public const VERSION = 'ai-health-1.0';

    /**
     * @return array{
     *     version: string,
     *     score: int,
     *     label: string,
     *     window_hours: int,
     *     metrics: array<string, float|int|bool|null>,
     *     llm: array{platform_enabled: bool, key_set: bool, applied_rate: float}
     * }
     */
    public function live(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $turns = WiseTurn::query()->where('created_at', '>=', $since);
        $turnCount = (clone $turns)->count();

        $gapRate = $turnCount > 0
            ? round(100 * (clone $turns)->where('gap', true)->count() / $turnCount, 1)
            : 0.0;

        $clarifyCount = (clone $turns)->where('decision->action', 'clarify')->count();
        $clarifyRate = $turnCount > 0 ? round(100 * $clarifyCount / $turnCount, 1) : 0.0;

        $avgConfidence = $turnCount > 0
            ? round((float) (clone $turns)->selectRaw($this->jsonAvgSql('decision', 'confidence').' as aggregate')->value('aggregate'))
            : 0.0;

        $avgLatency = $turnCount > 0
            ? round((float) (clone $turns)->avg('latency_ms'))
            : 0.0;

        $feedbacks = WiseFeedback::query()->where('created_at', '>=', $since);
        $fbCount = (clone $feedbacks)->count();
        $accept = (clone $feedbacks)->where('outcome', 'approved')->count();
        $reject = (clone $feedbacks)->where('outcome', 'rejected')->count();
        $edit = (clone $feedbacks)->where('outcome', 'edited')->count();
        $acceptRate = $fbCount > 0 ? round(100 * $accept / $fbCount, 1) : 0.0;
        $rejectRate = $fbCount > 0 ? round(100 * $reject / $fbCount, 1) : 0.0;
        $editRate = $fbCount > 0 ? round(100 * $edit / $fbCount, 1) : 0.0;

        $experienceNet = round((float) WiseExperienceSignal::query()
            ->where('created_at', '>=', $since)
            ->sum('weight'), 2);

        $llmApplied = (clone $turns)->where(function ($q) {
            $q->where('decision->language_llm_applied', true)
                ->orWhere('decision->language_llm->applied', true);
        })->count();
        $llmRate = $turnCount > 0 ? round(100 * $llmApplied / $turnCount, 1) : 0.0;

        $llmConfig = app(LlmLanguageConfig::class);

        // Weighted health: start 70, move by quality signals.
        $score = 70.0;
        $score += min(20, $acceptRate * 0.2);
        $score -= min(25, $rejectRate * 0.35);
        $score -= min(15, $gapRate * 0.2);
        $score -= min(10, $clarifyRate * 0.08);
        $score += min(8, max(-8, $experienceNet));
        $score += min(5, $avgConfidence / 25);
        if ($avgLatency > 800) {
            $score -= 5;
        }
        $score = (int) max(0, min(100, round($score)));

        $label = match (true) {
            $score >= 80 => 'Healthy',
            $score >= 60 => 'Fair',
            $score >= 40 => 'Needs attention',
            default => 'Critical',
        };

        return [
            'version' => self::VERSION,
            'score' => $score,
            'label' => $label,
            'window_hours' => $hours,
            'metrics' => [
                'turns' => $turnCount,
                'accept_rate' => $acceptRate,
                'reject_rate' => $rejectRate,
                'edit_rate' => $editRate,
                'gap_rate' => $gapRate,
                'clarify_rate' => $clarifyRate,
                'avg_confidence' => $avgConfidence,
                'avg_latency_ms' => $avgLatency,
                'experience_net' => $experienceNet,
                'llm_applied_rate' => $llmRate,
                'feedbacks' => $fbCount,
            ],
            'llm' => [
                'platform_enabled' => $llmConfig->enabled(),
                'key_set' => $llmConfig->hasApiKey(),
                'applied_rate' => $llmRate,
            ],
        ];
    }

    private function jsonAvgSql(string $column, string $key): string
    {
        $driver = DB::connection()->getDriverName();
        $path = '$.'.$key;

        return $driver === 'sqlite'
            ? "AVG(CAST(json_extract({$column}, '{$path}') AS REAL))"
            : "AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')) AS DECIMAL(10,2)))";
    }
}
