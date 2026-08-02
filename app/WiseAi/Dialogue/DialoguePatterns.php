<?php

namespace App\WiseAi\Dialogue;

/**
 * BD commerce dialogue acts — soft-linked to intent/memory, NOT nested under BCLC surfaces.
 * v1 adds thin scripts (clarify / assist_hint); Judge still owns the decision.
 */
final class DialoguePatterns
{
    public const VERSION = 'dialogue-0.2.0';

    /** @var array<string, array{label: string, family: string}> */
    public const CATALOG = [
        'open_greeting' => ['label' => 'Open greeting', 'family' => 'social'],
        'close_thanks' => ['label' => 'Close thanks', 'family' => 'social'],
        'soft_ack' => ['label' => 'Soft acknowledgement', 'family' => 'social'],
        'ask_price_bare' => ['label' => 'Ask price (no offer yet)', 'family' => 'commerce'],
        'ask_price_on_offer' => ['label' => 'Ask price on known offer', 'family' => 'commerce'],
        'ask_delivery' => ['label' => 'Ask delivery / courier', 'family' => 'commerce'],
        'ask_order_status' => ['label' => 'Ask order status', 'family' => 'commerce'],
        'raise_complaint' => ['label' => 'Raise complaint / return', 'family' => 'commerce'],
        'followup_carry' => ['label' => 'Short follow-up (intent carried)', 'family' => 'memory'],
        'soft_clarify_unknown' => ['label' => 'Soft clarify unknown utterance', 'family' => 'clarify'],
        'general_business' => ['label' => 'General business turn', 'family' => 'commerce'],
        'general_social' => ['label' => 'General social turn', 'family' => 'social'],
    ];
}
