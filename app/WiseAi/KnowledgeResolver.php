<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\KnowledgeLookup;
use App\WiseAi\Knowledge\KnowledgeSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ground business answers on published merchant knowledge (single source of truth).
 *
 * Latency: SQL token prefilter on match_text + scope pushdown + lean columns +
 * candidate cap + early-exit scoring. Never load the whole catalog into PHP.
 */
class KnowledgeResolver
{
    private const MIN_LEXICAL = 30;

    private const MIN_FINAL = 40;

    /** Clear single-winner threshold — below this, rivals may force a shortlist. */
    private const STRONG_WIN = 70;

    /** Second-best within this of best → ambiguous (when best < STRONG_WIN). */
    private const AMBIGUITY_MARGIN = 12;

    /** Min rival offers to clarify with a shortlist instead of guessing. */
    private const MIN_SHORTLIST = 2;

    /** Max titles shown in the clarify reply. */
    private const MAX_SHORTLIST = 5;

    /**
     * When true, skip platform-scoped (null key) rows — used by eval goldens for isolation.
     * Never set from public API clients.
     */
    private static bool $excludePlatform = false;

    public static function excludePlatform(bool $exclude): void
    {
        self::$excludePlatform = $exclude;
    }

    public static function excludesPlatform(): bool
    {
        return self::$excludePlatform;
    }

    /**
     * @var array<string, list<string>>
     */
    private const INTENT_HINTS = [
        'price' => ['দাম', 'প্রাইস', 'মূল্য', 'price', 'taka', 'টাকা', 'cost'],
        'delivery' => ['ডেলিভারি', 'কুরিয়ার', 'delivery', 'courier', 'shipping', 'চার্জ', 'charge', 'পাঠা'],
        'order_status' => ['অর্ডার', 'ট্র্যাক', 'পার্সেল', 'order', 'track', 'parcel', 'status'],
        'complaint' => ['রিটার্ন', 'ফেরত', 'অভিযোগ', 'return', 'refund', 'broken', 'damaged', 'warranty'],
    ];

    /**
     * @return array{item: WiseKnowledgeItem, score: int}|null
     */
    public function pricingMenu(WiseApiKey $apiKey): ?array
    {
        $item = WiseKnowledgeItem::query()
            ->where('status', 'published')
            ->where(function ($q) use ($apiKey) {
                $q->where('wise_api_key_id', $apiKey->id);
                if (! self::$excludePlatform) {
                    $q->orWhere(function ($q2) {
                        $q2->where('scope', KnowledgeSchema::SCOPE_PLATFORM)
                            ->whereNull('wise_api_key_id');
                    });
                }
            })
            ->whereIn('type', [
                KnowledgeSchema::KIND_FAQ,
                KnowledgeSchema::KIND_POLICY,
                KnowledgeSchema::KIND_FACT,
                KnowledgeSchema::KIND_OTHER,
                KnowledgeSchema::KIND_SCRIPT,
            ])
            ->where(function ($q) {
                $q->where('meta->pricing_menu', true)
                    ->orWhere('meta->pricing_menu', 1)
                    ->orWhere('meta->pricing_menu', '1')
                    ->orWhere('meta->pricing_menu', 'true');
            })
            ->orderByDesc('id')
            ->first([
                'id', 'wise_api_key_id', 'external_id', 'type', 'scope',
                'title', 'question', 'answer', 'keywords', 'meta', 'status', 'version',
            ]);

        if (! $item) {
            return null;
        }

        return ['item' => $item, 'score' => 100];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{knowledge_id?: int|null, external_id?: string|null, title?: string|null}|null  $productSubject
     * @return array{
     *     item?: WiseKnowledgeItem,
     *     score: int,
     *     candidates?: int,
     *     ambiguous?: bool,
     *     shortlist?: list<array{knowledge_id: int, title: string, external_id: string|null, score: int, type: string}>
     * }|null
     */
    public function resolve(
        WiseApiKey $apiKey,
        string $text,
        string $intent,
        array $context = [],
        ?array $productSubject = null,
    ): ?array {
        $normalized = KnowledgeLookup::normalize($text);
        $items = $this->candidates($apiKey, $normalized, $intent, $context, $productSubject);

        if ($items->isEmpty()) {
            return null;
        }

        $scored = [];
        foreach ($items as $item) {
            if (! $this->scopeApplies($item, $context, $productSubject)) {
                continue;
            }
            $score = $this->scoreItem($item, $normalized, $intent);
            if ($score < self::MIN_LEXICAL) {
                continue;
            }
            $scored[] = [
                'item' => $item,
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        $best = $scored[0];
        $bestScore = (int) $best['score'];
        $secondScore = isset($scored[1]) ? (int) $scored[1]['score'] : 0;

        // Asserted subject path should not shortlist — caller uses direct lookup.
        // Vague multi-offer hits → clarify shortlist (S5) instead of guessing.
        if ($productSubject === null && $this->isAmbiguousCluster($scored, $bestScore, $secondScore)) {
            $shortlist = $this->buildShortlist($scored);

            return [
                'ambiguous' => true,
                'score' => $bestScore,
                'candidates' => count($scored),
                'shortlist' => $shortlist,
            ];
        }

        if ($bestScore < self::MIN_FINAL) {
            return null;
        }

        return [
            'item' => $best['item'],
            'score' => $bestScore,
            'candidates' => count($scored),
        ];
    }

    /**
     * @param  list<array{item: WiseKnowledgeItem, score: int}>  $scored  desc by score
     */
    private function isAmbiguousCluster(array $scored, int $bestScore, int $secondScore): bool
    {
        if (count($scored) < self::MIN_SHORTLIST) {
            return false;
        }

        // Strong unique winner → answer.
        if ($bestScore >= self::STRONG_WIN && ($bestScore - $secondScore) >= self::AMBIGUITY_MARGIN) {
            return false;
        }

        $floor = max(self::MIN_LEXICAL, $bestScore - self::AMBIGUITY_MARGIN);
        $cluster = array_values(array_filter(
            $scored,
            static fn ($row) => (int) $row['score'] >= $floor
        ));

        // Prefer offer rows for shortlist ambiguity (catalog browse).
        $offers = array_values(array_filter(
            $cluster,
            static fn ($row) => (string) $row['item']->type === KnowledgeSchema::KIND_OFFER
        ));

        if (count($offers) >= self::MIN_SHORTLIST) {
            return true;
        }

        return count($cluster) >= self::MIN_SHORTLIST
            && $bestScore < self::STRONG_WIN;
    }

    /**
     * @param  list<array{item: WiseKnowledgeItem, score: int}>  $scored
     * @return list<array{knowledge_id: int, title: string, external_id: string|null, score: int, type: string}>
     */
    private function buildShortlist(array $scored): array
    {
        $out = [];
        foreach ($scored as $row) {
            /** @var WiseKnowledgeItem $item */
            $item = $row['item'];
            if ((string) $item->type !== KnowledgeSchema::KIND_OFFER) {
                continue;
            }
            $out[] = [
                'knowledge_id' => (int) $item->id,
                'title' => (string) $item->title,
                'external_id' => $item->external_id !== null ? (string) $item->external_id : null,
                'score' => (int) $row['score'],
                'type' => (string) $item->type,
            ];
            if (count($out) >= self::MAX_SHORTLIST) {
                break;
            }
        }

        // Fall back to any groundable kinds if no offers in cluster.
        if ($out === []) {
            foreach (array_slice($scored, 0, self::MAX_SHORTLIST) as $row) {
                $item = $row['item'];
                $out[] = [
                    'knowledge_id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'external_id' => $item->external_id !== null ? (string) $item->external_id : null,
                    'score' => (int) $row['score'],
                    'type' => (string) $item->type,
                ];
            }
        }

        return $out;
    }

    /**
     * BD clarify reply listing shortlist titles — never invents prices.
     *
     * @param  list<array{title: string}>  $shortlist
     */
    public function shortlistClarifyReply(array $shortlist): string
    {
        $lines = ['কয়েকটি মিল পাওয়া গেছে — কোনটির দাম/তথ্য জানতে চাচ্ছেন?'];
        $i = 1;
        foreach ($shortlist as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $lines[] = $i.'. '.$title;
            $i++;
        }
        $lines[] = 'নাম লিখে পাঠান, অথবা ছবি পাঠালে সঠিকটা ধরে বলে দিচ্ছি।';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{knowledge_id?: int|null, external_id?: string|null}|null  $productSubject
     * @return Collection<int, WiseKnowledgeItem>
     */
    private function candidates(
        WiseApiKey $apiKey,
        string $normalized,
        string $intent,
        array $context,
        ?array $productSubject,
    ): Collection {
        $hints = self::INTENT_HINTS[$intent] ?? [];
        $tokens = KnowledgeLookup::tokens($normalized, $hints);

        $query = $this->basePublishedQuery($apiKey);
        $this->applyScopePushdown($query, $context, $productSubject);

        if ($tokens !== []) {
            $filtered = (clone $query)
                ->where(function (Builder $q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $q->orWhere('match_text', 'like', KnowledgeLookup::likeContains($token));
                    }
                })
                ->orderByDesc('id')
                ->limit(KnowledgeLookup::CANDIDATE_LIMIT)
                ->get($this->leanColumns());

            if ($filtered->isNotEmpty()) {
                return $filtered;
            }
        }

        // Fallback: newest groundable rows (still capped) so empty match_text never bricks decide.
        return $query
            ->orderByDesc('id')
            ->limit(KnowledgeLookup::CANDIDATE_LIMIT)
            ->get($this->leanColumns());
    }

    /**
     * @return Builder<WiseKnowledgeItem>
     */
    private function basePublishedQuery(WiseApiKey $apiKey): Builder
    {
        return WiseKnowledgeItem::query()
            ->where('status', 'published')
            ->whereIn('type', KnowledgeSchema::groundableKinds())
            ->where(function ($q) use ($apiKey) {
                $q->where('wise_api_key_id', $apiKey->id);
                if (! self::$excludePlatform) {
                    $q->orWhere(function ($q2) {
                        $q2->where('scope', KnowledgeSchema::SCOPE_PLATFORM)
                            ->whereNull('wise_api_key_id');
                    });
                }
            });
    }

    /**
     * @param  Builder<WiseKnowledgeItem>  $query
     * @param  array<string, mixed>  $context
     * @param  array{knowledge_id?: int|null, external_id?: string|null}|null  $productSubject
     */
    private function applyScopePushdown(Builder $query, array $context, ?array $productSubject): void
    {
        $region = strtolower(trim((string) ($context['region'] ?? '')));
        $offerExt = trim((string) ($productSubject['external_id'] ?? ''));

        $query->where(function (Builder $q) use ($region, $offerExt) {
            $q->where(function (Builder $broad) {
                $broad->whereIn('scope', [
                    KnowledgeSchema::SCOPE_MERCHANT,
                    KnowledgeSchema::SCOPE_PLATFORM,
                ])->orWhereNull('scope')->orWhere('scope', '');
            });

            if ($offerExt !== '') {
                $q->orWhere(function (Builder $offer) use ($offerExt) {
                    $offer->where('scope', KnowledgeSchema::SCOPE_OFFER)
                        ->where('external_id', $offerExt);
                });
            }

            if ($region !== '') {
                $q->orWhere(function (Builder $reg) use ($region) {
                    $reg->where('scope', KnowledgeSchema::SCOPE_REGION)
                        ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.region"))) = ?', [$region]);
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    private function leanColumns(): array
    {
        return [
            'id', 'wise_api_key_id', 'external_id', 'type', 'scope',
            'title', 'question', 'answer', 'keywords', 'meta', 'match_text',
            'status', 'version',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{knowledge_id?: int|null, external_id?: string|null}|null  $productSubject
     */
    private function scopeApplies(WiseKnowledgeItem $item, array $context, ?array $productSubject): bool
    {
        $scope = (string) ($item->scope ?: KnowledgeSchema::SCOPE_MERCHANT);

        return match ($scope) {
            KnowledgeSchema::SCOPE_MERCHANT, KnowledgeSchema::SCOPE_PLATFORM => true,
            KnowledgeSchema::SCOPE_OFFER => $this->offerScopeMatches($item, $productSubject),
            KnowledgeSchema::SCOPE_REGION => $this->regionScopeMatches($item, $context),
            default => false,
        };
    }

    /**
     * @param  array{knowledge_id?: int|null, external_id?: string|null}|null  $productSubject
     */
    private function offerScopeMatches(WiseKnowledgeItem $item, ?array $productSubject): bool
    {
        if ($productSubject === null) {
            return false;
        }

        if (! empty($productSubject['knowledge_id']) && (int) $productSubject['knowledge_id'] === (int) $item->id) {
            return true;
        }

        $ext = trim((string) ($item->external_id ?? ''));
        $subjectExt = trim((string) ($productSubject['external_id'] ?? ''));

        return $ext !== '' && $subjectExt !== '' && $ext === $subjectExt;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function regionScopeMatches(WiseKnowledgeItem $item, array $context): bool
    {
        $itemRegion = strtolower(trim((string) ($item->meta['region'] ?? '')));
        $ctxRegion = strtolower(trim((string) ($context['region'] ?? '')));

        return $itemRegion !== '' && $ctxRegion !== '' && $itemRegion === $ctxRegion;
    }

    private function scoreItem(WiseKnowledgeItem $item, string $normalizedText, string $intent): int
    {
        $title = KnowledgeLookup::normalize((string) $item->title);
        $question = KnowledgeLookup::normalize((string) ($item->question ?? ''));
        $keywords = KnowledgeLookup::normalize(implode(' ', $item->keywords ?? []));
        $haystack = trim($title.' '.$question.' '.$keywords);
        if ($haystack === '' && ! empty($item->match_text)) {
            $haystack = (string) $item->match_text;
        }

        $lexical = 0;
        $strongSubstring = false;

        foreach ([$title, $question, $keywords] as $field) {
            if ($field === '') {
                continue;
            }
            if (str_contains($normalizedText, $field) || str_contains($field, $normalizedText)) {
                $lexical += 60;
                $strongSubstring = true;
            }

            foreach (preg_split('/\s+/u', $field) ?: [] as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }
                if (str_contains($normalizedText, $token)) {
                    $lexical += 10;
                }
            }
        }

        if ($lexical < self::MIN_LEXICAL) {
            return 0;
        }

        $hints = self::INTENT_HINTS[$intent] ?? null;
        if ($hints !== null && ! $strongSubstring && ! $this->haystackHasHint($haystack, $hints)) {
            return 0;
        }

        $score = $lexical;
        $kind = (string) $item->type;

        if (in_array($kind, [KnowledgeSchema::KIND_FAQ, KnowledgeSchema::KIND_FACT, KnowledgeSchema::KIND_OTHER], true)
            && in_array($intent, DecideEngine::BUSINESS_INTENTS, true)) {
            $score += 5;
        }
        if ($kind === KnowledgeSchema::KIND_POLICY && in_array($intent, ['complaint', 'delivery'], true)) {
            $score += 8;
        }
        if ($kind === KnowledgeSchema::KIND_OFFER && $intent === 'price') {
            $score += 8;
        }
        if ($kind === KnowledgeSchema::KIND_SCRIPT && in_array($intent, DecideEngine::BUSINESS_INTENTS, true)) {
            $score += 4;
        }
        if ($kind === KnowledgeSchema::KIND_CAMPAIGN && in_array($intent, ['price', 'delivery'], true)) {
            $score += 3;
        }

        $scope = (string) ($item->scope ?: KnowledgeSchema::SCOPE_MERCHANT);
        if ($scope === KnowledgeSchema::SCOPE_OFFER) {
            $score += 6;
        } elseif ($scope === KnowledgeSchema::SCOPE_REGION) {
            $score += 4;
        }

        return $score;
    }

    /**
     * @param  list<string>  $hints
     */
    private function haystackHasHint(string $haystack, array $hints): bool
    {
        foreach ($hints as $hint) {
            $hint = KnowledgeLookup::normalize($hint);
            if ($hint !== '' && str_contains($haystack, $hint)) {
                return true;
            }
        }

        return false;
    }
}
