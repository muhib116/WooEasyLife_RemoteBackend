<?php

namespace App\WiseAi\Experience;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseExperienceSignal;

/**
 * Soft experience hints — never flips Judge action or invents knowledge facts.
 */
class ExperienceResolver
{
    public const VERSION = ExperienceRecorder::VERSION;

    /**
     * Score signals for this turn (no decision mutation). Call before DialogueScripts
     * so preferred_script can influence enrich.
     *
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $configSnapshot
     * @return array{
     *     version: string,
     *     applied: bool,
     *     reason: string,
     *     matches: int,
     *     net_weight: float,
     *     style_bias: string|null,
     *     confidence_delta: int,
     *     preferred_script: string|null
     * }
     */
    public function preview(WiseApiKey $apiKey, array $decision, array $configSnapshot): array
    {
        $flags = is_array($configSnapshot['feature_flags'] ?? null)
            ? $configSnapshot['feature_flags']
            : [];
        if (empty($flags['experience_engine'])) {
            return [
                'version' => self::VERSION,
                'applied' => false,
                'reason' => 'flag_off',
                'matches' => 0,
                'net_weight' => 0.0,
                'style_bias' => null,
                'confidence_delta' => 0,
                'preferred_script' => null,
            ];
        }

        $intent = trim((string) ($decision['intent'] ?? ''));
        $action = (string) ($decision['action'] ?? '');
        $source = (string) ($decision['source'] ?? '');

        $query = WiseExperienceSignal::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('created_at', '>=', now()->subDays(90));

        // Intent must match exactly, or signal intent is explicit wildcard "*".
        // Null/empty intent signals do NOT pollute other intents.
        if ($intent !== '') {
            $query->where(function ($q) use ($intent) {
                $q->where('intent', $intent)->orWhere('intent', '*');
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('intent')->orWhere('intent', '')->orWhere('intent', '*');
            });
        }

        $rows = $query->orderByDesc('id')->limit(200)->get();
        $net = 0.0;
        $matches = 0;
        $scriptScores = [];

        foreach ($rows as $row) {
            $score = (float) $row->weight;
            if ($action !== '' && $row->action === $action) {
                $score *= 1.25;
            }
            if ($source !== '' && $row->source === $source) {
                $score *= 1.15;
            }
            $net += $score;
            $matches++;
            $pk = (string) ($row->pattern_key ?? '');
            if (str_starts_with($pk, 'script:')) {
                $sid = substr($pk, 7);
                if ($sid !== '') {
                    $scriptScores[$sid] = ($scriptScores[$sid] ?? 0) + $score;
                }
            }
        }

        $preferredScript = null;
        if ($scriptScores !== []) {
            arsort($scriptScores);
            $top = array_key_first($scriptScores);
            if ($top !== null && ($scriptScores[$top] ?? 0) >= 2.0) {
                $preferredScript = (string) $top;
            }
        }

        $delta = (int) max(-8, min(8, round($net)));
        $styleBias = null;
        if ($net >= 3) {
            $styleBias = 'confident_short';
        } elseif ($net <= -3) {
            $styleBias = 'careful_clarify';
        }

        return [
            'version' => self::VERSION,
            'applied' => $matches > 0,
            'reason' => $matches > 0 ? 'ok' : 'no_signals',
            'matches' => $matches,
            'net_weight' => round($net, 2),
            'style_bias' => $styleBias,
            'confidence_delta' => $delta,
            'preferred_script' => $preferredScript,
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $configSnapshot
     * @param  array<string, mixed>|null  $preview  Reuse {@see preview()} when already computed
     * @return array{
     *     decision: array<string, mixed>,
     *     experience: array<string, mixed>
     * }
     */
    public function apply(WiseApiKey $apiKey, array $decision, array $configSnapshot, ?array $preview = null): array
    {
        $experience = $preview ?? $this->preview($apiKey, $decision, $configSnapshot);

        $delta = (int) ($experience['confidence_delta'] ?? 0);
        $confidence = (int) ($decision['confidence'] ?? 0);
        if ($delta !== 0 && $confidence > 0) {
            $decision['confidence'] = max(1, min(99, $confidence + $delta));
        }

        $styleBias = $experience['style_bias'] ?? null;
        if (
            is_string($styleBias) && $styleBias !== ''
            && ($decision['source'] ?? '') !== 'knowledge'
            && empty($decision['dialogue']['assist_hint'])
        ) {
            $decision['dialogue'] = is_array($decision['dialogue'] ?? null) ? $decision['dialogue'] : [];
            $decision['dialogue']['assist_hint'] = $styleBias === 'confident_short'
                ? 'Merchants accepted similar replies — keep it short.'
                : 'Similar replies were rejected — clarify carefully before asserting.';
        }

        $decision['experience'] = $experience;

        return ['decision' => $decision, 'experience' => $experience];
    }

    /**
     * Net experience weight for a key in the last N hours (dashboard).
     */
    public function netWeight(?int $apiKeyId = null, int $hours = 24): float
    {
        $q = WiseExperienceSignal::query()
            ->where('created_at', '>=', now()->subHours($hours));
        if ($apiKeyId !== null && $apiKeyId > 0) {
            $q->where('wise_api_key_id', $apiKeyId);
        }

        return round((float) $q->sum('weight'), 2);
    }
}
