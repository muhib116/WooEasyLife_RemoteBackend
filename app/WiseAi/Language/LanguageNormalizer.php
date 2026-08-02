<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguageEntry;
use Illuminate\Support\Facades\Cache;

/**
 * Deterministic Language Intelligence pipeline — runs BEFORE intent detection.
 * Normalization only (not translation). Ambiguous tokens are left untouched.
 */
class LanguageNormalizer
{
    /** Short TTL — publish/promote also busts explicitly. */
    private const ENTRY_CACHE_TTL = 45;

    /** @var array<string, list<array{type: string, from_text: string, to_text: string, wise_api_key_id: int|null}>> */
    private static array $entryMemo = [];

    public function __construct(
        private PlatformLexicon $lexicon,
        private CorpusResolver $corpus,
    ) {}

    public static function forgetEntryCache(?int $apiKeyId = null): void
    {
        unset($apiKeyId); // epoch bump invalidates every key pack
        Cache::forever('wise_lang_entries_epoch', ((int) Cache::get('wise_lang_entries_epoch', 0)) + 1);
        self::$entryMemo = [];
    }

    /**
     * @return list<array{id: int, version: int, type: string, from_text: string, to_text: string, wise_api_key_id: int|null}>
     */
    private function publishedEntries(?WiseApiKey $apiKey): array
    {
        $epoch = (int) Cache::get('wise_lang_entries_epoch', 0);
        $keyId = $apiKey?->id ?? 0;
        $cacheKey = "wise_lang_entries:{$epoch}:{$keyId}";

        if (isset(self::$entryMemo[$cacheKey])) {
            return self::$entryMemo[$cacheKey];
        }

        $rows = Cache::remember($cacheKey, self::ENTRY_CACHE_TTL, function () use ($apiKey) {
            return WiseLanguageEntry::query()
                ->where('status', 'published')
                ->where('enabled', true)
                ->where(function ($q) use ($apiKey) {
                    $q->whereNull('wise_api_key_id');
                    if ($apiKey) {
                        $q->orWhere('wise_api_key_id', $apiKey->id);
                    }
                })
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'version', 'type', 'from_text', 'to_text', 'wise_api_key_id'])
                ->map(fn (WiseLanguageEntry $e) => [
                    'id' => (int) $e->id,
                    'version' => (int) $e->version,
                    'type' => (string) $e->type,
                    'from_text' => (string) $e->from_text,
                    'to_text' => (string) ($e->to_text ?? ''),
                    'wise_api_key_id' => $e->wise_api_key_id,
                ])
                ->all();
        });

        return self::$entryMemo[$cacheKey] = $rows;
    }

    /**
     * @return array{
     *     raw: string,
     *     canonical: string,
     *     dict_version: string,
     *     corpus_snapshot: array<string, mixed>,
     *     concepts_hit: list<string>,
     *     stages: list<array{stage: string, after: string}>,
     *     rules_applied: list<array{type: string, from: string, to: string}>,
     *     emoji_signals: list<array{emoji: string, signal: string, polarity: string}>,
     *     commerce_terms: list<string>,
     *     fillers_removed: list<string>,
     *     ambiguous: list<string>,
     *     unknown_tokens: list<string>
     * }
     */
    public function normalize(string $raw, ?WiseApiKey $apiKey = null, ?string $channel = null, ?string $region = null): array
    {
        $region = RegionCode::normalize((string) ($region ?? '')) ?? RegionCode::resolve($apiKey);
        $resolved = $this->corpus->resolve($apiKey, $channel, $region);
        $pack = $this->packFromResolved($resolved);
        [$pack, $overlaySeal] = $this->mergeOverrides($pack, $apiKey);
        $conceptIndex = is_array($resolved['concept_hits'] ?? null) ? $resolved['concept_hits'] : [];
        $rules = [];
        $stages = [];
        $emojiSignals = [];
        $commerceTerms = [];
        $fillersRemoved = [];
        $ambiguous = [];
        $conceptsHit = [];

        $text = $raw;
        $text = $this->unicodeCleanup($text);
        $stages[] = ['stage' => 'unicode_whitespace', 'after' => $text];

        [$text, $emojiSignals] = $this->extractEmoji($text, $pack['emoji']);
        if ($emojiSignals !== []) {
            $stages[] = ['stage' => 'emoji_extract', 'after' => $text];
        }

        $text = $this->collapseElongation($text);
        $stages[] = ['stage' => 'elongation', 'after' => $text];

        // Phrase maps (multi-word) before single-token maps. Compiled artifacts only — no surface SQL.
        foreach (['phonetic', 'banglish', 'commerce', 'messenger', 'abbrev', 'sms'] as $type) {
            $map = is_array($pack[$type] ?? null) ? $pack[$type] : [];
            [$text, $applied] = $this->applyMap($text, $map, $type, $ambiguous);
            foreach ($applied as $row) {
                $rules[] = $row;
                if ($type === 'commerce') {
                    $commerceTerms[] = $row['to'];
                }
                $from = mb_strtolower((string) $row['from']);
                if (isset($conceptIndex[$from])) {
                    $conceptsHit[] = (string) $conceptIndex[$from];
                }
            }
            if ($applied !== []) {
                $stages[] = ['stage' => $type, 'after' => $text];
            }
        }

        [$text, $removed] = $this->stripFillers($text, $pack['filler']);
        $fillersRemoved = $removed;
        if ($removed !== []) {
            $stages[] = ['stage' => 'filler_strip', 'after' => $text];
            foreach ($removed as $f) {
                $rules[] = ['type' => 'filler', 'from' => $f, 'to' => ''];
            }
        }

        $ambiguousTokens = is_array($resolved['ambiguous'] ?? null)
            ? $resolved['ambiguous']
            : PlatformLexicon::AMBIGUOUS;
        foreach ($ambiguousTokens as $token) {
            if (preg_match('/(?:^|\s)'.preg_quote((string) $token, '/').'(?:\s|$|[!?.,])/u', $text) === 1) {
                $ambiguous[] = (string) $token;
            }
        }

        $canonical = $this->unicodeCleanup($text);

        // Emoji-only messages → structured text for intent (still evidence-free social).
        if ($canonical === '' && $emojiSignals !== []) {
            foreach ($emojiSignals as $signal) {
                if (($signal['signal'] ?? '') === 'thanks') {
                    $canonical = 'thank you';
                    break;
                }
            }
            if ($canonical === '') {
                $canonical = (($emojiSignals[0]['polarity'] ?? '') === 'negative') ? 'no' : 'okay';
            }
            $stages[] = ['stage' => 'emoji_to_text', 'after' => $canonical];
        }

        if ($canonical === '') {
            $canonical = $this->unicodeCleanup($raw);
        }

        $unknown = $this->unknownTokens($canonical, $pack, $ambiguous);

        $dictVersion = (string) ($resolved['dict_version'] ?? PlatformLexicon::DICT_VERSION);
        if (($overlaySeal['content_hash'] ?? '') !== '') {
            $dictVersion .= '+ov:'.substr((string) $overlaySeal['content_hash'], 0, 8);
        }

        $corpusSnapshot = is_array($resolved['corpus_snapshot'] ?? null)
            ? $resolved['corpus_snapshot']
            : [];
        $corpusSnapshot['overlays'] = $overlaySeal;

        return [
            'raw' => $raw,
            'canonical' => $canonical,
            'dict_version' => $dictVersion,
            'corpus_snapshot' => $corpusSnapshot,
            'concepts_hit' => array_values(array_unique($conceptsHit)),
            'stages' => $stages,
            'rules_applied' => $rules,
            'emoji_signals' => $emojiSignals,
            'commerce_terms' => array_values(array_unique($commerceTerms)),
            'fillers_removed' => $fillersRemoved,
            'ambiguous' => array_values(array_unique($ambiguous)),
            'unknown_tokens' => $unknown,
        ];
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function packFromResolved(array $resolved): array
    {
        $maps = is_array($resolved['maps'] ?? null) ? $resolved['maps'] : [];
        $pack = [
            'abbrev' => [],
            'sms' => [],
            'banglish' => [],
            'phonetic' => [],
            'commerce' => [],
            'messenger' => [],
            'filler' => is_array($resolved['filler'] ?? null) ? $resolved['filler'] : [],
            'emoji' => is_array($resolved['emoji'] ?? null) ? $resolved['emoji'] : [],
        ];
        foreach (LanguageCorpus::MAP_CATEGORIES as $cat) {
            $pack[$cat] = is_array($maps[$cat] ?? null) ? $maps[$cat] : [];
        }

        // Safety: empty artifact set → PlatformLexicon (CorpusResolver already falls back).
        if (! ($resolved['from_artifacts'] ?? false) && $pack['abbrev'] === []) {
            return $this->lexicon->pack() + ['messenger' => []];
        }

        return $pack;
    }

    /**
     * Apply merchant/platform overlays and return a Replay-honest seal of what was applied.
     *
     * @param  array<string, mixed>  $pack
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function mergeOverrides(array $pack, ?WiseApiKey $apiKey): array
    {
        $sealEntries = [];
        $applied = [];

        // Human-approved DB entries (platform + this merchant key) — never auto-learned.
        foreach ($this->publishedEntries($apiKey) as $entry) {
            $pack = $this->applyEntry(
                $pack,
                (string) $entry['type'],
                (string) $entry['from_text'],
                (string) ($entry['to_text'] ?? ''),
            );
            $row = [
                'id' => (int) $entry['id'],
                'version' => (int) $entry['version'],
                'type' => (string) $entry['type'],
                'from' => mb_strtolower(trim((string) $entry['from_text'])),
                'to' => trim((string) ($entry['to_text'] ?? '')),
                'wise_api_key_id' => $entry['wise_api_key_id'],
                'source' => 'language_entry',
            ];
            $sealEntries[] = $row;
            $applied[] = $row;
        }

        $metaCount = 0;
        if ($apiKey) {
            $overrides = $apiKey->meta['language_overrides'] ?? null;
            if (is_array($overrides)) {
                foreach ($overrides as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $type = (string) ($row['type'] ?? '');
                    $from = mb_strtolower(trim((string) ($row['from'] ?? '')));
                    $to = trim((string) ($row['to'] ?? ''));
                    $pack = $this->applyEntry($pack, $type, $from, $to);
                    $metaCount++;
                    $applied[] = [
                        'id' => null,
                        'version' => null,
                        'type' => $type,
                        'from' => $from,
                        'to' => $to,
                        'wise_api_key_id' => $apiKey->id,
                        'source' => 'meta_override',
                    ];
                }
            }
        }

        usort($applied, function (array $a, array $b): int {
            return [$a['source'], $a['type'], $a['from'], (string) ($a['id'] ?? '')]
                <=> [$b['source'], $b['type'], $b['from'], (string) ($b['id'] ?? '')];
        });

        $contentHash = $applied === []
            ? ''
            : hash('sha256', (string) json_encode($applied, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            $pack,
            [
                'content_hash' => $contentHash,
                'entry_count' => count($sealEntries),
                'meta_override_count' => $metaCount,
                'entries' => array_map(static fn (array $e) => [
                    'id' => $e['id'],
                    'version' => $e['version'],
                    'type' => $e['type'],
                    'from' => $e['from'],
                    'to' => $e['to'],
                    'wise_api_key_id' => $e['wise_api_key_id'],
                    'source' => $e['source'],
                ], $applied),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>
     */
    private function applyEntry(array $pack, string $type, string $from, string $to): array
    {
        $from = mb_strtolower(trim($from));
        $to = trim($to);
        if ($from === '' || in_array($from, PlatformLexicon::AMBIGUOUS, true)) {
            return $pack;
        }
        if ($type === 'filler') {
            if (! in_array($from, $pack['filler'], true)) {
                $pack['filler'][] = $from;
            }

            return $pack;
        }
        if ($to === '') {
            return $pack;
        }
        if (! isset($pack[$type]) || ! is_array($pack[$type])) {
            $pack[$type] = [];
        }
        $pack[$type][$from] = $to;

        return $pack;
    }

    private function unicodeCleanup(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    /**
     * @param  array<string, array{signal: string, polarity: string}>  $emojiMap
     * @return array{0: string, 1: list<array{emoji: string, signal: string, polarity: string}>}
     */
    private function extractEmoji(string $text, array $emojiMap): array
    {
        $signals = [];
        foreach ($emojiMap as $emoji => $meta) {
            if ($emoji !== '' && str_contains($text, $emoji)) {
                $count = mb_substr_count($text, $emoji);
                for ($i = 0; $i < $count; $i++) {
                    $signals[] = [
                        'emoji' => $emoji,
                        'signal' => $meta['signal'],
                        'polarity' => $meta['polarity'],
                    ];
                }
                $text = str_replace($emoji, ' ', $text);
            }
        }

        // Strip other emoji-ish symbols (keep Bangla/Latin).
        $text = (string) preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', ' ', $text);

        return [$this->unicodeCleanup($text), $signals];
    }

    private function collapseElongation(string $text): string
    {
        // okkk → okk (then abbrev map), thankuuu → thankuu
        $text = (string) preg_replace('/(.)\1{2,}/u', '$1$1', $text);

        return $text;
    }

    /**
     * @param  array<string, string>  $map
     * @param  list<string>  $ambiguous
     * @return array{0: string, 1: list<array{type: string, from: string, to: string}>}
     */
    private function applyMap(string $text, array $map, string $type, array &$ambiguous): array
    {
        $applied = [];
        $keys = array_keys($map);
        usort($keys, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($keys as $from) {
            $from = mb_strtolower($from);
            if ($from === '') {
                continue;
            }
            if (in_array($from, PlatformLexicon::AMBIGUOUS, true)) {
                if (preg_match('/(?:^|\s)'.preg_quote($from, '/').'(?:\s|$|[!?.,])/u', $text) === 1) {
                    $ambiguous[] = $from;
                }
                continue;
            }

            $to = $map[$from];
            if (str_contains($from, ' ')) {
                $pattern = '/'.preg_quote($from, '/').'/u';
            } else {
                $pattern = '/(?:^|\s)('.preg_quote($from, '/').')(?:\s|$|[!?.,])/u';
            }

            $new = preg_replace_callback($pattern, function ($m) use ($from, $to, $type, &$applied) {
                $applied[] = ['type' => $type, 'from' => $from, 'to' => $to];
                if (isset($m[1])) {
                    return str_replace($m[1], $to, $m[0]);
                }

                return $to;
            }, $text, -1, $count);

            if (is_string($new) && $count > 0) {
                $text = $this->unicodeCleanup($new);
            }
        }

        return [$text, $applied];
    }

    /**
     * @param  list<string>  $fillers
     * @return array{0: string, 1: list<string>}
     */
    private function stripFillers(string $text, array $fillers): array
    {
        $removed = [];
        usort($fillers, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($fillers as $filler) {
            $filler = mb_strtolower($filler);
            $pattern = '/(?:^|\s)('.preg_quote($filler, '/').')(?:\s|$|[!?.,])/u';
            $new = preg_replace_callback($pattern, function ($m) use ($filler, &$removed) {
                $removed[] = $filler;

                return ' ';
            }, $text, -1, $count);
            if (is_string($new) && $count > 0) {
                $text = $this->unicodeCleanup($new);
            }
        }

        return [$text, array_values(array_unique($removed))];
    }

    /**
     * @param  array<string, mixed>  $pack
     * @param  list<string>  $ambiguous
     * @return list<string>
     */
    private function unknownTokens(string $canonical, array $pack, array $ambiguous): array
    {
        $known = [];
        foreach (['abbrev', 'sms', 'banglish', 'phonetic', 'commerce', 'messenger'] as $type) {
            foreach (($pack[$type] ?? []) as $from => $to) {
                foreach (preg_split('/\s+/u', $from) ?: [] as $t) {
                    $known[$t] = true;
                }
                foreach (preg_split('/\s+/u', mb_strtolower($to)) ?: [] as $t) {
                    $known[$t] = true;
                }
            }
        }
        foreach ($pack['filler'] as $f) {
            $known[$f] = true;
        }
        foreach ([
            'দাম', 'কত', 'টাকা', 'আছে', 'হবে', 'ডেলিভারি', 'অর্ডার', 'স্টক',
            'please', 'thank', 'you', 'okay', 'price', 'delivery', 'charge',
            'cash', 'on', 'shipping', 'courier', 'stock', 'available',
            // Common English fillers — do not spam Language Review
            'hello', 'the', 'and', 'for', 'with', 'this', 'that', 'have', 'from',
            'what', 'when', 'where', 'want', 'need', 'now', 'yes', 'not',
        ] as $t) {
            $known[$t] = true;
        }

        $unknown = [];
        foreach (preg_split('/\s+/u', $canonical) ?: [] as $token) {
            $token = trim($token, '!?.,।"\'');
            if ($token === '' || mb_strlen($token) < 3) {
                continue;
            }
            if (isset($known[mb_strtolower($token)])) {
                continue;
            }
            if (in_array(mb_strtolower($token), $ambiguous, true)) {
                continue;
            }
            // Skip pure Bangla/English common short — flag latin gibberish / odd tokens
            if (preg_match('/^[a-z0-9\']+$/u', $token) === 1) {
                $unknown[] = $token;
            }
        }

        return array_values(array_unique(array_slice($unknown, 0, 12)));
    }
}
