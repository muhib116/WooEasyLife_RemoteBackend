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
        foreach ($this->evidenceDigitTokens($evidence) as $token) {
            $allowed[$token] = true;
        }

        foreach ($this->digitTokens($rewrite) as $token => $_) {
            if (! isset($allowed[$token])) {
                return false;
            }
        }

        return true;
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
     * @param  array<string, mixed>|null  $evidence
     * @return array<string, true>
     */
    private function evidenceDigitTokens(?array $evidence): array
    {
        if (! is_array($evidence)) {
            return [];
        }

        $blob = json_encode($evidence, JSON_UNESCAPED_UNICODE);
        if (! is_string($blob)) {
            return [];
        }

        return $this->digitTokens($blob);
    }

    private function normalizeDigits(string $text): string
    {
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $lat = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(array_merge($bn, $ar), array_merge($lat, $lat), $text);
    }
}
