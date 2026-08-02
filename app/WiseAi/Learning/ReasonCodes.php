<?php

namespace App\WiseAi\Learning;

/**
 * Human-guided learning reason taxonomy (Wave B2).
 * Reject/edit feedback should pick a code so Coach/BI can learn later — never silent.
 */
class ReasonCodes
{
    public const VERSION = '1.0';

    public const WRONG_FACT = 'wrong_fact';

    public const WRONG_OFFER = 'wrong_offer';

    public const TONE = 'tone';

    public const LANGUAGE = 'language';

    public const OUTDATED = 'outdated';

    public const MISSING_KNOWLEDGE = 'missing_knowledge';

    public const POLICY = 'policy';

    public const OTHER = 'other';

    /** Soft defaults from older UI — still accepted. */
    public const LEGACY = [
        'assist_approve',
        'assist_reject',
        'assist_edit',
        'playground_approve',
        'playground_reject',
    ];

    /**
     * Codes shown when rejecting / editing a suggestion.
     *
     * @return array<string, string> code => label
     */
    public static function reviewChoices(): array
    {
        return [
            self::WRONG_FACT => 'Wrong fact / answer',
            self::WRONG_OFFER => 'Wrong offer / product',
            self::MISSING_KNOWLEDGE => 'Missing knowledge (should be a gap)',
            self::OUTDATED => 'Outdated knowledge',
            self::TONE => 'Tone / voice wrong',
            self::LANGUAGE => 'Language / wording wrong',
            self::POLICY => 'Policy / safety concern',
            self::OTHER => 'Other',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::reviewChoices()),
            self::LEGACY,
        )));
    }

    public static function isValid(string $code): bool
    {
        return in_array($code, self::all(), true);
    }

    public static function label(string $code): string
    {
        return self::reviewChoices()[$code] ?? $code;
    }
}
