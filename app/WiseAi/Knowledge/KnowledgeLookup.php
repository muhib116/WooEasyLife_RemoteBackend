<?php

namespace App\WiseAi\Knowledge;

/**
 * Shared low-latency helpers for knowledge / offer lookup as catalogs grow.
 * Prefer SQL candidate narrowing + lean rows over scanning hundreds in PHP.
 */
class KnowledgeLookup
{
    /** Hard cap of rows scored in PHP per decide turn. */
    public const CANDIDATE_LIMIT = 48;

    /** Stop scoring once a clearly strong lexical hit is found. */
    public const EARLY_EXIT_SCORE = 95;

    /**
     * Significant tokens for SQL prefilter (longest first, capped).
     *
     * @param  list<string>  $extra  e.g. intent hint tokens
     * @return list<string>
     */
    public static function tokens(string $normalizedText, array $extra = [], int $max = 6): array
    {
        $parts = preg_split('/\s+/u', $normalizedText) ?: [];
        $tokens = [];

        foreach (array_merge($parts, $extra) as $token) {
            $token = mb_strtolower(trim((string) $token));
            if (mb_strlen($token) < 3) {
                continue;
            }
            // Skip pure punctuation noise
            if (preg_match('/^[\p{P}\p{S}]+$/u', $token) === 1) {
                continue;
            }
            $tokens[$token] = mb_strlen($token);
        }

        arsort($tokens, SORT_NUMERIC);

        return array_slice(array_keys($tokens), 0, $max);
    }

    public static function buildMatchText(?string $title, ?string $question, array|string|null $keywords): string
    {
        $kw = is_array($keywords) ? implode(' ', $keywords) : (string) ($keywords ?? '');
        $raw = trim(($title ?? '').' '.($question ?? '').' '.$kw);
        $raw = mb_strtolower($raw);

        return (string) preg_replace('/\s+/u', ' ', $raw);
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    /**
     * Escape LIKE wildcards in user tokens.
     */
    public static function likeContains(string $token): string
    {
        $token = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token);

        return '%'.$token.'%';
    }
}
