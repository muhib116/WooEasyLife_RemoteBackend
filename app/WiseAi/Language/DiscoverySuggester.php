<?php

namespace App\WiseAi\Language;

/**
 * Suggest pack + category placement for discovery candidates (human still approves).
 */
class DiscoverySuggester
{
    /**
     * @return array{kind: string, pack_slug: string, category: string, concept_key: string}
     */
    public function suggest(string $surface, ?string $sample = null, ?string $region = null): array
    {
        $surface = mb_strtolower(trim($surface));
        $sample = mb_strtolower(trim((string) $sample));
        $hasSpace = str_contains($surface, ' ');
        $hasBangla = preg_match('/\p{Bengali}/u', $surface.$sample) === 1;
        $region = RegionCode::normalize((string) ($region ?? ''));

        if (preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]+$/u', $surface) === 1) {
            return $this->row('emoji', 'core-bd', 'emoji', 'emoji.'.md5($surface));
        }

        if ($regional = $this->looksRegional($surface, $sample, $region)) {
            return $this->row(
                $hasSpace ? 'phrase' : 'banglish',
                $regional,
                'banglish',
                'banglish.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface)
            );
        }

        if ($this->looksCommerce($surface, $sample)) {
            return $this->row(
                $hasSpace ? 'phrase' : 'token',
                'commerce',
                'commerce',
                'commerce.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface)
            );
        }

        if ($this->looksMessenger($surface, $sample)) {
            return $this->row(
                $hasSpace ? 'phrase' : 'abbrev',
                'messenger',
                $hasSpace ? 'messenger' : 'abbrev',
                'messenger.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface)
            );
        }

        // Grow the merchant's regional pack from Discovery (not hand-seeded stems).
        // Skip when the surface matches a *foreign* region's seed stems.
        if (
            $region
            && ! $this->matchesForeignRegionalStem($surface, $sample, $region)
            && ($hasBangla || $this->looksBanglish($surface, $sample) || $this->looksLocalGrowthToken($surface))
        ) {
            return $this->row(
                $hasSpace ? 'phrase' : 'banglish',
                RegionCode::packSlug($region),
                'banglish',
                'banglish.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface)
            );
        }

        if ($hasBangla || $this->looksBanglish($surface, $sample)) {
            return $this->row(
                $hasSpace ? 'phrase' : 'banglish',
                'core-bd',
                'banglish',
                'banglish.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface)
            );
        }

        if (! $hasSpace && mb_strlen($surface) <= 5 && preg_match('/^[a-z0-9.]+$/u', $surface) === 1) {
            return $this->row('abbrev', 'core-bd', 'abbrev', 'abbrev.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface));
        }

        if ($hasSpace) {
            return $this->row('phrase', 'core-bd', 'banglish', 'phrase.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface));
        }

        return $this->row('token', 'core-bd', 'banglish', 'token.'.preg_replace('/[^a-z0-9_]+/u', '_', $surface));
    }

    /**
     * @return array{kind: string, pack_slug: string, category: string, concept_key: string}
     */
    private function row(string $kind, string $pack, string $category, string $concept): array
    {
        return [
            'kind' => $kind,
            'pack_slug' => $pack,
            'category' => $category,
            'concept_key' => mb_substr($concept, 0, 120),
        ];
    }

    private function looksRegional(string $surface, string $sample, ?string $region): ?string
    {
        $catalog = RegionCode::seedCatalog();
        // When merchant/turn region is set, only match that region's stems (no cross-pack remap).
        $scan = ($region && isset($catalog[$region]))
            ? [$region => $catalog[$region]]
            : $catalog;

        foreach ($scan as $code => $def) {
            foreach (array_keys($def['banglish']) as $from) {
                if ($this->phraseBounded((string) $from, $surface, $sample)) {
                    return RegionCode::packSlug($code);
                }
            }
        }

        return null;
    }

    /** True when surface matches another region's seed stems (do not grow merchant pack with foreign dialect). */
    private function matchesForeignRegionalStem(string $surface, string $sample, string $merchantRegion): bool
    {
        foreach (RegionCode::seedCatalog() as $code => $def) {
            if ($code === $merchantRegion) {
                continue;
            }
            foreach (array_keys($def['banglish']) as $from) {
                if ($this->phraseBounded((string) $from, $surface, $sample)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Unknown local-looking tokens worth parking on the opted-in regional pack. */
    private function looksLocalGrowthToken(string $surface): bool
    {
        if (str_contains($surface, ' ')) {
            return true;
        }
        if (mb_strlen($surface) < 4 || mb_strlen($surface) > 24) {
            return false;
        }

        // Latin banglish-ish (not pure digits / not english filler handled elsewhere).
        return preg_match('/^[a-z][a-z0-9\']*$/u', $surface) === 1;
    }

    /** Whole-phrase / whole-token match — avoid substring false hits (goita→oita). */
    private function phraseBounded(string $phrase, string $surface, string $sample): bool
    {
        $phrase = mb_strtolower(trim($phrase));
        if ($phrase === '') {
            return false;
        }
        if ($surface === $phrase) {
            return true;
        }
        $quoted = preg_quote($phrase, '/');
        $quoted = str_replace('\ ', '\s+', $quoted);
        $blob = $surface.' '.$sample;

        return preg_match('/(?:^|[^\p{L}\p{N}_])'.$quoted.'(?:$|[^\p{L}\p{N}_])/u', $blob) === 1;
    }

    private function looksCommerce(string $surface, string $sample): bool
    {
        $blob = $surface.' '.$sample;

        return (bool) preg_match('/\b(cod|bkash|bikash|nagad|rocket|price|dam|delivery|stock|order|payment)\b/u', $blob);
    }

    private function looksMessenger(string $surface, string $sample): bool
    {
        $blob = $surface.' '.$sample;

        return (bool) preg_match('/\b(inbox|dm|brb|ttyl|msg|seen|reply)\b/u', $blob);
    }

    private function looksBanglish(string $surface, string $sample): bool
    {
        $blob = $surface.' '.$sample;

        return (bool) preg_match('/\b(koto|ase|hobe|lagbe|pamu|vai|bhai|taka|dam)\b/u', $blob);
    }
}
