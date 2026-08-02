<?php

namespace App\WiseAi\Knowledge;

/**
 * Evolutionary knowledge kinds + scopes (Wave B1).
 * Intent Contract stays in brain/governance — never duplicated onto every row.
 */
class KnowledgeSchema
{
    /** Seal on every turn for Replay honesty. */
    public const VERSION = '1.1-kinds';

    /** Q→A and catalog kinds merchants already use. */
    public const KIND_FAQ = 'faq';

    public const KIND_OFFER = 'product'; // BC name — sellable offer

    public const KIND_POLICY = 'policy';

    public const KIND_FACT = 'fact';

    public const KIND_SCRIPT = 'script';

    public const KIND_CAMPAIGN = 'campaign';

    public const KIND_VOICE = 'voice'; // tone guide — not customer-groundable

    public const KIND_OTHER = 'other'; // legacy alias of fact

    public const SCOPE_MERCHANT = 'merchant';

    public const SCOPE_OFFER = 'offer';

    public const SCOPE_REGION = 'region';

    public const SCOPE_PLATFORM = 'platform';

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [
            self::KIND_FAQ,
            self::KIND_OFFER,
            self::KIND_POLICY,
            self::KIND_FACT,
            self::KIND_SCRIPT,
            self::KIND_CAMPAIGN,
            self::KIND_VOICE,
            self::KIND_OTHER,
        ];
    }

    /**
     * Kinds that may ground a customer-facing suggested_reply.
     *
     * @return list<string>
     */
    public static function groundableKinds(): array
    {
        return [
            self::KIND_FAQ,
            self::KIND_OFFER,
            self::KIND_POLICY,
            self::KIND_FACT,
            self::KIND_SCRIPT,
            self::KIND_CAMPAIGN,
            self::KIND_OTHER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [
            self::SCOPE_MERCHANT,
            self::SCOPE_OFFER,
            self::SCOPE_REGION,
            self::SCOPE_PLATFORM,
        ];
    }

    public static function isGroundable(string $kind): bool
    {
        return in_array($kind, self::groundableKinds(), true);
    }
}
