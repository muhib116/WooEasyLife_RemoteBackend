<?php

namespace App\WiseAi\Intelligence;

/**
 * Founder fleet alert thresholds (Wave C2). Versioned so dashboards stay honest.
 * Not sealed on every turn — evaluated live against MetricDefinitions rates.
 */
class FleetAlerts
{
    public const VERSION = '1.1';

    /** Min turns in window before rate alerts fire. */
    public const MIN_TURNS_FOR_RATE = 20;

    /** Min reviewed feedbacks before reject-rate alerts. */
    public const MIN_REVIEWS_FOR_RATE = 10;

    public const GAP_RATE_WARN = 40.0;

    public const REJECT_RATE_WARN = 30.0;

    public const GAPS_OPEN_WARN = 25;

    public const ASSIST_PENDING_WARN = 50;

    public const LATENCY_MS_WARN = 500;

    public const STALE_DAYS = 14;

    public const AI_HEALTH_WARN = 60;

    /**
     * @return list<array{id: string, severity: string, label: string, definition: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'id' => 'high_gap_rate',
                'severity' => 'warning',
                'label' => 'High gap rate',
                'definition' => 'gap_rate ≥ '.self::GAP_RATE_WARN.'% with ≥ '.self::MIN_TURNS_FOR_RATE.' turns',
            ],
            [
                'id' => 'high_reject_rate',
                'severity' => 'warning',
                'label' => 'High reject rate',
                'definition' => 'reject_rate ≥ '.self::REJECT_RATE_WARN.'% with ≥ '.self::MIN_REVIEWS_FOR_RATE.' reviews',
            ],
            [
                'id' => 'queue_gaps',
                'severity' => 'warning',
                'label' => 'Gap queue backlog',
                'definition' => 'open gaps ≥ '.self::GAPS_OPEN_WARN,
            ],
            [
                'id' => 'queue_assist',
                'severity' => 'warning',
                'label' => 'Assist backlog',
                'definition' => 'assist pending ≥ '.self::ASSIST_PENDING_WARN,
            ],
            [
                'id' => 'slow_latency',
                'severity' => 'info',
                'label' => 'Slow decide latency',
                'definition' => 'avg latency_ms ≥ '.self::LATENCY_MS_WARN,
            ],
            [
                'id' => 'stale_key',
                'severity' => 'info',
                'label' => 'Stale active key',
                'definition' => 'active key, no turns in window, last_used older than '.self::STALE_DAYS.' days (or never)',
            ],
            [
                'id' => 'auto_enabled',
                'severity' => 'critical',
                'label' => 'Auto mode enabled',
                'definition' => 'Merchant allow_auto=true — autonomy must stay earned/default-off',
            ],
            [
                'id' => 'low_ai_health',
                'severity' => 'warning',
                'label' => 'Low AI health (Dashboard)',
                'definition' => 'Dashboard HealAlerts when AiHealthScore < '.self::AI_HEALTH_WARN.' (observe-only; see heal-alerts)',
            ],
        ];
    }
}
