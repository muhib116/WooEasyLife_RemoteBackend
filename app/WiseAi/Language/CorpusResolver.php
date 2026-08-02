<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguageArtifact;
use App\Models\WiseAi\WiseLanguagePackAssignment;
use Illuminate\Support\Facades\Cache;

/**
 * Loads compiled pack artifacts for a key (assignment order).
 * Never scans wise_language_surfaces on the decide path.
 *
 * Apply order (last wins): platform → region → channel → industry → wise_api_key,
 * then numeric priority ascending within the same target type.
 */
class CorpusResolver
{
    private const CACHE_TTL = 120;

    /** Lower rank applied first; higher specificity overlays later. */
    private const TARGET_RANK = [
        'platform' => 10,
        'region' => 20,
        'channel' => 30,
        'industry' => 40,
        'wise_api_key' => 50,
    ];

    /** @var array<string, array<string, mixed>> */
    private static array $memo = [];

    public static function forgetCache(): void
    {
        Cache::forever('wise_bclc_epoch', ((int) Cache::get('wise_bclc_epoch', 0)) + 1);
        self::$memo = [];
    }

    /**
     * Merged runtime pack + seal snapshot metadata (no wall-clock; sealer stamps sealed_at).
     *
     * @return array{
     *     maps: array<string, array<string, string>>,
     *     filler: list<string>,
     *     emoji: array<string, array{signal: string, polarity: string}>,
     *     ambiguous: list<string>,
     *     concept_hits: array<string, string>,
     *     dict_version: string,
     *     corpus_snapshot: array<string, mixed>,
     *     from_artifacts: bool
     * }
     */
    public function resolve(?WiseApiKey $apiKey = null, ?string $channel = null, ?string $region = null): array
    {
        $epoch = (int) Cache::get('wise_bclc_epoch', 0);
        $keyId = $apiKey?->id ?? 0;
        $channelKey = $channel !== null && $channel !== '' ? mb_strtolower(trim($channel)) : '';
        $regionKey = RegionCode::normalize((string) ($region ?? '')) ?? '';
        $memoKey = "bclc:{$epoch}:{$keyId}:{$channelKey}:{$regionKey}";

        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $resolved = Cache::remember($memoKey, self::CACHE_TTL, function () use ($apiKey, $channelKey, $regionKey) {
            return $this->resolveFresh(
                $apiKey,
                $channelKey !== '' ? $channelKey : null,
                $regionKey !== '' ? $regionKey : null,
            );
        });

        return self::$memo[$memoKey] = $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFresh(?WiseApiKey $apiKey, ?string $channel, ?string $region = null): array
    {
        $assignments = $this->assignmentsFor($apiKey, $channel, $region);
        if ($assignments === []) {
            return $this->fallbackFromLexicon('no_assignments');
        }

        $packIds = array_values(array_unique(array_map(fn ($a) => (int) $a['pack_id'], $assignments)));
        $artifacts = WiseLanguageArtifact::query()
            ->whereIn('pack_id', $packIds)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->get()
            ->unique('pack_id')
            ->keyBy('pack_id');

        $platformPackIds = [];
        foreach ($assignments as $a) {
            if (($a['target_type'] ?? '') === 'platform') {
                $platformPackIds[(int) $a['pack_id']] = true;
            }
        }

        $missing = [];
        $softDropped = [];
        foreach ($packIds as $packId) {
            if (! $artifacts->has($packId)) {
                $missing[] = $packId;
            }
        }

        if ($artifacts->isEmpty()) {
            return $this->fallbackFromLexicon('incomplete_artifacts', $missing, $packIds);
        }

        // Platform packs are required. Missing region/channel/key overlays are soft-dropped
        // so an optional regional artifact never nukes Core/Commerce/Messenger.
        $platformMissing = array_values(array_filter(
            $missing,
            fn (int $id) => isset($platformPackIds[$id])
        ));
        if ($platformMissing !== []) {
            return $this->fallbackFromLexicon('incomplete_artifacts', $platformMissing, $packIds);
        }
        if ($missing !== []) {
            $softDropped = $missing;
            $assignments = array_values(array_filter(
                $assignments,
                fn (array $a) => ! in_array((int) $a['pack_id'], $missing, true)
            ));
            if ($assignments === []) {
                return $this->fallbackFromLexicon('incomplete_artifacts', $missing, $packIds);
            }
        }

        usort($assignments, function (array $a, array $b): int {
            $rank = ($a['type_rank'] <=> $b['type_rank']);
            if ($rank !== 0) {
                return $rank;
            }

            return $a['priority'] <=> $b['priority'];
        });

        $maps = [];
        foreach (LanguageCorpus::MAP_CATEGORIES as $cat) {
            $maps[$cat] = [];
        }
        $filler = [];
        $emoji = [];
        $ambiguous = PlatformLexicon::AMBIGUOUS;
        $conceptHits = [];
        $sealPacks = [];
        $assignmentIds = [];

        foreach ($assignments as $row) {
            /** @var WiseLanguageArtifact|null $artifact */
            $artifact = $artifacts->get($row['pack_id']);
            if (! $artifact) {
                continue;
            }
            $decoded = $artifact->decoded();
            if ($decoded === []) {
                if (($row['target_type'] ?? '') === 'platform') {
                    return $this->fallbackFromLexicon('empty_artifact', [$row['pack_id']], $packIds);
                }
                $softDropped[] = (int) $row['pack_id'];

                continue;
            }

            foreach (LanguageCorpus::MAP_CATEGORIES as $cat) {
                $slice = $decoded['maps'][$cat] ?? [];
                if (is_array($slice)) {
                    foreach ($slice as $from => $to) {
                        $maps[$cat][(string) $from] = (string) $to;
                    }
                }
            }

            foreach ($decoded['filler'] ?? [] as $f) {
                $filler[] = (string) $f;
            }
            foreach ($decoded['emoji'] ?? [] as $em => $meta) {
                if (is_array($meta)) {
                    $emoji[(string) $em] = [
                        'signal' => (string) ($meta['signal'] ?? 'emotion'),
                        'polarity' => (string) ($meta['polarity'] ?? 'neutral'),
                    ];
                }
            }
            foreach ($decoded['concept_hits'] ?? [] as $from => $conceptKey) {
                $conceptHits[(string) $from] = (string) $conceptKey;
            }

            $sealPacks[] = [
                'id' => (int) $artifact->pack_id,
                'slug' => (string) ($decoded['pack_slug'] ?? ''),
                'version' => (string) $artifact->pack_version,
                'artifact_hash' => (string) $artifact->content_hash,
                'target_type' => (string) $row['target_type'],
            ];
            $assignmentIds[] = (int) $row['id'];
        }

        $filler = array_values(array_unique($filler));
        $dictVersion = 'bclc:'.implode('+', array_map(
            fn ($p) => ($p['slug'] ?: 'pack').'@'.$p['version'].':'.substr($p['artifact_hash'], 0, 8),
            $sealPacks
        ));

        $snapshot = [
            'protocol_version' => LanguageCorpus::PROTOCOL_VERSION,
            'packs' => $sealPacks,
            'compiler_version' => LanguageCorpus::COMPILER_VERSION,
            'assignment_ids' => $assignmentIds,
            'assignment_key' => $this->assignmentKey($apiKey, $channel, $region),
            'region' => $region,
            'from_artifacts' => true,
        ];
        if ($softDropped !== []) {
            $snapshot['soft_dropped_pack_ids'] = array_values(array_unique($softDropped));
        }

        return [
            'maps' => $maps,
            'filler' => $filler,
            'emoji' => $emoji,
            'ambiguous' => array_values(array_unique($ambiguous)),
            'concept_hits' => $conceptHits,
            'dict_version' => $dictVersion !== 'bclc:' ? $dictVersion : PlatformLexicon::DICT_VERSION,
            'corpus_snapshot' => $snapshot,
            'from_artifacts' => true,
        ];
    }

    /**
     * @return list<array{id: int, pack_id: int, priority: int, target_type: string, type_rank: int}>
     */
    private function assignmentsFor(?WiseApiKey $apiKey, ?string $channel, ?string $region = null): array
    {
        $rows = WiseLanguagePackAssignment::query()
            ->where('enabled', true)
            ->where(function ($q) use ($apiKey, $channel, $region) {
                $q->where(function ($q2) {
                    $q2->where('target_type', 'platform')
                        ->where(function ($q3) {
                            $q3->whereNull('target_id')->orWhere('target_id', 'default');
                        });
                });
                if ($region) {
                    $q->orWhere(function ($q2) use ($region) {
                        $q2->where('target_type', 'region')
                            ->where('target_id', $region);
                    });
                }
                if ($channel) {
                    $q->orWhere(function ($q2) use ($channel) {
                        $q2->where('target_type', 'channel')
                            ->where('target_id', $channel);
                    });
                }
                if ($apiKey) {
                    $q->orWhere(function ($q2) use ($apiKey) {
                        $q2->where('target_type', 'wise_api_key')
                            ->where('target_id', (string) $apiKey->id);
                    });
                }
            })
            ->whereHas('pack', fn ($q) => $q->where('status', 'published'))
            ->get(['id', 'pack_id', 'priority', 'target_type']);

        return $rows->map(function ($r) {
            $type = (string) $r->target_type;

            return [
                'id' => (int) $r->id,
                'pack_id' => (int) $r->pack_id,
                'priority' => (int) $r->priority,
                'target_type' => $type,
                'type_rank' => self::TARGET_RANK[$type] ?? 15,
            ];
        })->all();
    }

    private function assignmentKey(?WiseApiKey $apiKey, ?string $channel, ?string $region = null): string
    {
        $parts = ['platform-default'];
        if ($region) {
            $parts[] = 'region:'.$region;
        }
        if ($channel) {
            $parts[] = 'channel:'.$channel;
        }
        if ($apiKey) {
            $parts[] = 'key:'.$apiKey->id;
        }

        return implode('+', $parts);
    }

    /**
     * @param  list<int>  $missing
     * @param  list<int>  $expected
     * @return array<string, mixed>
     */
    private function fallbackFromLexicon(string $reason, array $missing = [], array $expected = []): array
    {
        $lex = app(PlatformLexicon::class)->pack();
        $maps = [];
        foreach (LanguageCorpus::MAP_CATEGORIES as $cat) {
            $maps[$cat] = [];
        }
        foreach (['abbrev', 'sms', 'banglish', 'phonetic', 'commerce'] as $cat) {
            foreach ($lex[$cat] as $from => $to) {
                $maps[$cat][mb_strtolower((string) $from)] = (string) $to;
            }
        }

        return [
            'maps' => $maps,
            'filler' => array_map(fn ($f) => mb_strtolower((string) $f), $lex['filler']),
            'emoji' => $lex['emoji'],
            'ambiguous' => PlatformLexicon::AMBIGUOUS,
            'concept_hits' => [],
            'dict_version' => PlatformLexicon::DICT_VERSION,
            'corpus_snapshot' => [
                'protocol_version' => LanguageCorpus::PROTOCOL_VERSION,
                'packs' => [],
                'compiler_version' => LanguageCorpus::COMPILER_VERSION,
                'assignment_ids' => [],
                'assignment_key' => 'lexicon-fallback',
                'from_artifacts' => false,
                'fallback' => 'platform_lexicon',
                'fallback_reason' => $reason,
                'missing_pack_ids' => array_values($missing),
                'expected_pack_ids' => array_values($expected),
            ],
            'from_artifacts' => false,
        ];
    }
}
