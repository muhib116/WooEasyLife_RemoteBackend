<?php

namespace App\WiseAi\Governance;

/**
 * Platform AI Constitution — rare changes, always sealed by version on each turn.
 */
class Constitution
{
    public const VERSION = 'constitution-1.0.0';

    /**
     * @return list<string>
     */
    public static function principles(): array
    {
        return [
            'knowledge_first',
            'evidence_first',
            'human_first',
            'bangladesh_first',
            'merchant_first',
            'trust_first',
            'learning_forever',
            'never_invent_business_facts',
            'judge_before_language',
            'human_guided_learning_only',
            'auto_default_off',
        ];
    }
}
