<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguageReview;
use Illuminate\Support\Facades\DB;

/**
 * BCLC Discovery Queue v0: unknown tokens/phrases → ranked review (never auto-promoted).
 * Decide path only upserts rows + provisional score; cross-key rank refresh is deferred.
 */
class LanguageReviewIngestor
{
    /** @var array<string, array<string, true>> */
    private static array $knownSurfacesByRegion = [];

    public function __construct(
        private DiscoverySuggester $suggester,
        private DiscoveryRanker $ranker,
    ) {}

    public static function forgetKnownSurfaceCache(): void
    {
        self::$knownSurfacesByRegion = [];
    }

    /**
     * @param  array<string, mixed>  $language  LanguageNormalizer result
     */
    public function ingest(WiseApiKey $apiKey, array $language, ?int $turnId = null, ?string $channel = null, ?string $region = null): void
    {
        $region = RegionCode::normalize((string) ($region ?? '')) ?? RegionCode::resolve($apiKey);
        $candidates = $this->candidates($language, $region);
        if ($candidates === []) {
            return;
        }

        $sample = mb_substr((string) ($language['raw'] ?? ''), 0, 500);
        $channel = $channel !== null && $channel !== '' ? mb_strtolower(trim($channel)) : null;
        $dirty = [];

        foreach ($candidates as $surface) {
            DB::transaction(function () use ($apiKey, $surface, $sample, $turnId, $channel, $region, &$dirty) {
                $suggestion = $this->suggester->suggest($surface, $sample, $region);

                $row = WiseLanguageReview::query()
                    ->where('wise_api_key_id', $apiKey->id)
                    ->where('token', $surface)
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    if ($row->status === 'ignored') {
                        $row->status = 'open';
                        $row->handled_at = null;
                    }
                    // Promoted stays promoted — still count hits for analytics.
                    $row->hit_count = (int) $row->hit_count + 1;
                    $row->last_turn_id = $turnId;
                    $row->last_seen_at = now();

                    // Train imports own channel + proposed expansion in sample_text.
                    // Live Discovery must not wipe one-click Promote prefills.
                    $isTrainOpen = $row->status === 'open' && ($row->channel ?? '') === 'train';
                    if (! $isTrainOpen) {
                        $row->sample_text = $sample !== '' ? $sample : $row->sample_text;
                        if ($channel) {
                            $row->channel = $channel;
                        }
                        if ($row->status === 'open') {
                            $row->kind = $suggestion['kind'];
                            $row->suggested_pack_slug = $suggestion['pack_slug'];
                            $row->suggested_category = $suggestion['category'];
                            $row->suggested_concept_key = $suggestion['concept_key'];
                        }
                    }

                    $row->rank_score = $this->ranker->provisionalScore(
                        (int) $row->hit_count,
                        max(1, (int) $row->key_breadth),
                        $row->last_seen_at
                    );
                    $row->save();
                } else {
                    WiseLanguageReview::create([
                        'wise_api_key_id' => $apiKey->id,
                        'token' => $surface,
                        'kind' => $suggestion['kind'],
                        'channel' => $channel,
                        'sample_text' => $sample !== '' ? $sample : null,
                        'hit_count' => 1,
                        'status' => 'open',
                        'suggested_pack_slug' => $suggestion['pack_slug'],
                        'suggested_category' => $suggestion['category'],
                        'suggested_concept_key' => $suggestion['concept_key'],
                        'rank_score' => $this->ranker->provisionalScore(1, 1, now()),
                        'key_breadth' => 1,
                        'first_turn_id' => $turnId,
                        'last_turn_id' => $turnId,
                        'last_seen_at' => now(),
                    ]);
                }

                $dirty[] = $surface;
            });
        }

        $this->ranker->queueRefresh($dirty);
    }

    /**
     * @param  array<string, mixed>  $language
     * @return list<string>
     */
    private function candidates(array $language, ?string $region = null): array
    {
        $out = [];
        $tokens = $language['unknown_tokens'] ?? [];
        if (is_array($tokens)) {
            foreach (array_slice($tokens, 0, 8) as $token) {
                $token = mb_strtolower(trim((string) $token));
                if ($token === '' || mb_strlen($token) < 3) {
                    continue;
                }
                if (in_array($token, PlatformLexicon::AMBIGUOUS, true)) {
                    continue;
                }
                if ($this->isKnownSurface($token, $region)) {
                    continue;
                }
                $out[$token] = true;
            }
        }

        // Phrase candidates from raw — skip anything already in compiled/lexicon maps.
        $raw = mb_strtolower(trim((string) ($language['raw'] ?? '')));
        if ($raw !== '' && preg_match_all('/\b([a-z]{2,}(?:\s+[a-z]{2,}){1,2})\b/u', $raw, $m)) {
            foreach (array_slice($m[1] ?? [], 0, 6) as $phrase) {
                $phrase = trim((string) $phrase);
                if (mb_strlen($phrase) < 5 || mb_strlen($phrase) > 120) {
                    continue;
                }
                if (! preg_match('/\b(koto|ase|hobe|dam|price|delivery|stock|cod|bkash|nagad|order|msg|inbox)\b/u', $phrase)) {
                    continue;
                }
                if ($this->phraseAlreadyCovered($phrase, $region)) {
                    continue;
                }
                $out[$phrase] = true;
            }
        }

        return array_slice(array_keys($out), 0, 10);
    }

    /** Skip phrases that are (or contain) a published lexicon/compiled surface. */
    private function phraseAlreadyCovered(string $phrase, ?string $region = null): bool
    {
        if ($this->isKnownSurface($phrase, $region)) {
            return true;
        }

        $words = preg_split('/\s+/u', $phrase) ?: [];
        $n = count($words);
        for ($len = min(3, $n); $len >= 2; $len--) {
            for ($i = 0; $i <= $n - $len; $i++) {
                $sub = implode(' ', array_slice($words, $i, $len));
                if ($this->isKnownSurface($sub, $region)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isKnownSurface(string $surface, ?string $region = null): bool
    {
        $known = $this->knownSurfaces($region);

        return isset($known[$surface]);
    }

    /** @return array<string, true> */
    private function knownSurfaces(?string $region = null): array
    {
        $cacheKey = $region ?: '_platform';
        if (isset(self::$knownSurfacesByRegion[$cacheKey])) {
            return self::$knownSurfacesByRegion[$cacheKey];
        }

        $known = [];
        $lex = app(PlatformLexicon::class)->pack();
        foreach (['abbrev', 'sms', 'banglish', 'phonetic', 'commerce'] as $cat) {
            foreach (array_keys($lex[$cat] ?? []) as $from) {
                $known[mb_strtolower((string) $from)] = true;
            }
        }
        foreach ($lex['filler'] ?? [] as $f) {
            $known[mb_strtolower((string) $f)] = true;
        }

        // Include regional compiled maps when region is opted in.
        $resolved = app(CorpusResolver::class)->resolve(null, null, $region);
        foreach ($resolved['maps'] ?? [] as $map) {
            if (! is_array($map)) {
                continue;
            }
            foreach (array_keys($map) as $from) {
                $known[mb_strtolower((string) $from)] = true;
            }
        }
        foreach ($resolved['filler'] ?? [] as $f) {
            $known[mb_strtolower((string) $f)] = true;
        }

        return self::$knownSurfacesByRegion[$cacheKey] = $known;
    }
}
