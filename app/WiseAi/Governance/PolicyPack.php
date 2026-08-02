<?php

namespace App\WiseAi\Governance;

/**
 * Platform policy pack — modes, evidence rule, contract version.
 */
class PolicyPack
{
    public const VERSION = 'policy-pack-1.0.0';

    public const DEFAULT_MODE = 'assist';

    /** @var list<string> */
    public const ALLOWED_MODES = ['shadow', 'assist', 'auto'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'mode' => self::DEFAULT_MODE,
            'allow_auto' => false,
            'evidence_rule' => 'business_requires_knowledge',
            'contracts_version' => 'v0',
            'learning' => 'human_guided',
            'feature_flags' => [
                'language_intelligence' => true,
                'explain_api' => true,
                'pricing_menu' => true,
                'llm_language' => true,
                'experience_engine' => true,
                'auto_send' => false,
            ],
        ];
    }
}
