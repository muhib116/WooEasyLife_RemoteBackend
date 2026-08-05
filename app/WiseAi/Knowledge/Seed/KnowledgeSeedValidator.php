<?php

namespace App\WiseAi\Knowledge\Seed;

use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Language\RegionCode;
use InvalidArgumentException;

/**
 * Hard gates so seeders stay Trust-First (no invented commerce facts).
 */
final class KnowledgeSeedValidator
{
    /**
     * Reject invented money / phones / discount percents in answers.
     * Allows phrases that explicitly refuse to invent ("অনুমান…না", "দিব না").
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string> error messages (empty = ok)
     */
    public function validateCatalog(array $items, string $scopeExpected): array
    {
        $errors = [];
        $slugs = [];

        if ($items === []) {
            return ['catalog is empty'];
        }

        foreach ($items as $i => $row) {
            $label = 'item['.$i.']';
            foreach (['slug', 'type', 'title', 'question', 'answer', 'keywords'] as $req) {
                if (! isset($row[$req]) || $row[$req] === '' || $row[$req] === []) {
                    $errors[] = "{$label} missing {$req}";
                }
            }
            if (! is_array($row['keywords'] ?? null)) {
                $errors[] = "{$label} keywords must be list";
            }

            $slug = (string) ($row['slug'] ?? '');
            $region = (string) ($row['region'] ?? ($row['meta']['region'] ?? ''));
            $uniq = $scopeExpected === KnowledgeSchema::SCOPE_REGION
                ? $region.'|'.$slug
                : $slug;
            if ($slug !== '') {
                if (isset($slugs[$uniq])) {
                    $errors[] = 'duplicate slug '.($scopeExpected === KnowledgeSchema::SCOPE_REGION ? "{$region}/{$slug}" : $slug);
                }
                $slugs[$uniq] = true;
            }

            $type = (string) ($row['type'] ?? '');
            if ($type !== '' && ! in_array($type, KnowledgeSchema::kinds(), true)) {
                $errors[] = "{$label} invalid type {$type}";
            }
            if ($type === KnowledgeSchema::KIND_VOICE) {
                $errors[] = "{$label} voice is not groundable — do not seed as customer answer";
            }

            $answer = (string) ($row['answer'] ?? '');
            $errors = array_merge($errors, $this->answerFactGuards($answer, $label));

            if ($scopeExpected === KnowledgeSchema::SCOPE_REGION) {
                if (RegionCode::normalize($region) === null) {
                    $errors[] = "{$label} invalid region";
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function answerFactGuards(string $answer, string $label): array
    {
        $errors = [];

        // Money amounts — ban digits+currency entirely (refuse nearby is not enough).
        if (preg_match('/(?:৳|tk\.?|taka|bdt|টাকা)\s*\d|\d+\s*(?:৳|tk\.?|taka|bdt|টাকা)/ui', $answer)) {
            $errors[] = "{$label} answer contains money amount — platform/region seeds must not invent fees/prices";
        }
        // Bare multi-digit / percent: refuse must be in the SAME sentence as the number.
        if ($this->hasUnguardedNumericLiteral($answer)) {
            $errors[] = "{$label} answer contains numeric literal without refuse phrasing — remove store-specific numbers";
        }
        if (preg_match('/(?:\+?88)?01[3-9]\d{8}/u', $answer)) {
            $errors[] = "{$label} answer contains phone number";
        }
        if ($this->hasUnguardedPercent($answer)) {
            $errors[] = "{$label} answer contains percent discount";
        }

        return $errors;
    }

    private function sentenceHasRefuse(string $sentence): bool
    {
        return (bool) preg_match('/অনুমান|আন্দাজ|দিব না|বলব না|বানাব না|invent/ui', $sentence);
    }

    /**
     * Bare multi-digit (60, 120, ১২০) allowed only inside a refuse sentence.
     */
    private function hasUnguardedNumericLiteral(string $answer): bool
    {
        foreach ($this->sentences($answer) as $sentence) {
            if (
                preg_match('/(?<![0-9])\d{2,}(?![0-9])/u', $sentence) === 1
                && ! $this->sentenceHasRefuse($sentence)
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasUnguardedPercent(string $answer): bool
    {
        foreach ($this->sentences($answer) as $sentence) {
            if (
                preg_match('/\d+\s*%/u', $sentence) === 1
                && ! $this->sentenceHasRefuse($sentence)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function sentences(string $answer): array
    {
        $parts = preg_split('/(?<=[।!?.\n])\s*/u', trim($answer)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out !== [] ? $out : [trim($answer)];
    }

    /**
     * Ensure regional banglish stems are unique across hubs (Discovery foreign-stem safety).
     *
     * @return list<string>
     */
    public function validateRegionalLexicon(): array
    {
        $errors = [];
        $seen = [];
        foreach (RegionCode::seedCatalog() as $code => $def) {
            foreach (array_keys($def['banglish'] ?? []) as $from) {
                $key = mb_strtolower(trim((string) $from));
                if ($key === '') {
                    $errors[] = "empty banglish key in {$code}";

                    continue;
                }
                if (isset($seen[$key]) && $seen[$key] !== $code) {
                    $errors[] = "banglish stem “{$key}” shared by {$seen[$key]} and {$code} — Discovery cross-pollute risk";
                } else {
                    $seen[$key] = $code;
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     */
    public function assertValid(array $errors, string $context): void
    {
        if ($errors !== []) {
            throw new InvalidArgumentException($context.":\n - ".implode("\n - ", $errors));
        }
    }
}
