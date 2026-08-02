<?php

namespace App\WiseAi\Language;

/**
 * BCLC protocol constants — author → compile → assign → seal.
 * Intent/Entity stay outside the corpus hierarchy (soft links only).
 */
final class LanguageCorpus
{
    public const COMPILER_VERSION = '1.0.1';

    /** Protocol / schema wave for sealed snapshots. */
    public const PROTOCOL_VERSION = 'bclc-0.1.2';

    /** Categories that compile into string→string maps. */
    public const MAP_CATEGORIES = ['abbrev', 'sms', 'banglish', 'phonetic', 'commerce', 'messenger'];

    /** Categories that compile into strip lists. */
    public const LIST_CATEGORIES = ['filler'];

    /** Categories that compile into emoji signal maps. */
    public const EMOJI_CATEGORY = 'emoji';
}
