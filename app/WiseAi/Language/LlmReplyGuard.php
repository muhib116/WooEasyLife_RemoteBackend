<?php

namespace App\WiseAi\Language;

/**
 * Reject LLM rewrites that invent new digit/currency facts.
 */
class LlmReplyGuard
{
    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function accepts(string $original, string $rewrite, ?array $evidence = null): bool
    {
        $rewrite = trim($rewrite);
        if ($rewrite === '' || mb_strlen($rewrite) > 5000) {
            return false;
        }

        $allowed = $this->digitTokens($original);
        foreach ($this->evidenceDigitTokens($evidence) as $token => $_) {
            $allowed[$token] = true;
        }

        foreach ($this->digitTokens($rewrite) as $token => $_) {
            if (! isset($allowed[$token])) {
                return false;
            }
        }

        // Do not escalate casual hi/hello into a salam reply.
        if ($this->hasSalamMarker($rewrite) && ! $this->hasSalamMarker($original)) {
            return false;
        }

        return true;
    }

    private function hasSalamMarker(string $text): bool
    {
        $t = mb_strtolower($text);

        return str_contains($t, 'ওয়ালাইকুম')
            || str_contains($t, 'ওয়ালাইকুম')
            || str_contains($t, 'আসসালামু')
            || str_contains($t, 'assalam')
            || str_contains($t, 'walaikum')
            || str_contains($t, 'wa alaikum');
    }

    /**
     * @return array<string, true>
     */
    public function digitTokens(string $text): array
    {
        $normalized = $this->normalizeDigits($text);
        preg_match_all('/\d+(?:[.,]\d+)?/u', $normalized, $matches);
        $out = [];
        foreach ($matches[0] ?? [] as $m) {
            $out[(string) $m] = true;
        }

        return $out;
    }

    /**
     * Digits allowed from evidence prose only (answers/titles/tool values).
     * Never whitelist metadata numerals like knowledge id or match score.
     *
     * @param  array<string, mixed>|null  $evidence
     * @return array<string, true>
     */
    private function evidenceDigitTokens(?array $evidence): array
    {
        if (! is_array($evidence)) {
            return [];
        }

        $parts = [];
        foreach (['answer', 'title', 'question'] as $key) {
            if (isset($evidence[$key]) && is_string($evidence[$key])) {
                $parts[] = $evidence[$key];
            }
        }

        foreach (['chunks', 'evidence_pack'] as $listKey) {
            if (! is_array($evidence[$listKey] ?? null)) {
                continue;
            }
            foreach ($evidence[$listKey] as $chunk) {
                if (! is_array($chunk)) {
                    continue;
                }
                foreach (['answer', 'title', 'question', 'value'] as $key) {
                    if (isset($chunk[$key]) && (is_string($chunk[$key]) || is_numeric($chunk[$key]))) {
                        $parts[] = (string) $chunk[$key];
                    }
                }
            }
        }

        if (is_array($evidence['tool_facts'] ?? null)) {
            foreach ($evidence['tool_facts'] as $fact) {
                if (! is_array($fact)) {
                    continue;
                }
                if (isset($fact['value']) && (is_string($fact['value']) || is_numeric($fact['value']))) {
                    $parts[] = (string) $fact['value'];
                }
            }
        }

        if ($parts === []) {
            return [];
        }

        return $this->digitTokens(implode("\n", $parts));
    }

    private function normalizeDigits(string $text): string
    {
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $lat = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(array_merge($bn, $ar), array_merge($lat, $lat), $text);
    }
}
