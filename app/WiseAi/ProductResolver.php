<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\KnowledgeLookup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolve product subject from channel context or message text.
 *
 * @phpstan-type ProductSubject array{
 *     knowledge_id: int|null,
 *     title: string,
 *     source: string,
 *     external_id?: string|null
 * }
 */
class ProductResolver
{
    private const MIN_SCORE = 35;

    /**
     * Channel-asserted product from any commerce platform / catalog.
     * Hub matches generic external_id (+ optional meta.platform) — not Woo-specific.
     *
     * @param  array<string, mixed>  $context
     * @return ProductSubject|null
     */
    public function fromContext(WiseApiKey $apiKey, array $context): ?array
    {
        // product_id = catalog offer id on any platform (physical, digital, service, …).
        $productId = $context['product_id'] ?? $context['external_id'] ?? $context['offer_id'] ?? $context['wc_product_id'] ?? null;
        $platform = isset($context['platform']) ? trim((string) $context['platform']) : '';

        if ($productId !== null && $productId !== '') {
            $externalId = (string) $productId;
            $query = WiseKnowledgeItem::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('status', 'published')
                ->where('type', 'product')
                ->where('external_id', $externalId);

            if ($platform !== '') {
                $query->where(function ($q) use ($platform) {
                    $q->where('meta->platform', $platform)
                        ->orWhereNull('meta->platform')
                        ->orWhere('meta->platform', '');
                });
            }

            $item = $query->first();

            if ($item) {
                return [
                    'knowledge_id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'source' => 'context.product_id',
                    'external_id' => $externalId,
                ];
            }

            $name = isset($context['product_name']) ? trim((string) $context['product_name']) : '';

            return [
                'knowledge_id' => null,
                'title' => $name !== '' ? $name : 'Product #'.$externalId,
                'source' => 'context.product_id',
                'external_id' => $externalId,
            ];
        }

        $sku = $context['product_sku'] ?? $context['sku'] ?? null;
        if (is_string($sku) && trim($sku) !== '') {
            $sku = trim($sku);
            $item = WiseKnowledgeItem::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('status', 'published')
                ->where('type', 'product')
                ->where('meta->sku', $sku)
                ->first();

            if ($item) {
                return [
                    'knowledge_id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'source' => 'context.product_sku',
                    'external_id' => $item->external_id,
                ];
            }

            // SKU asserted but no published offer → subject for gap (not clarify).
            $name = isset($context['product_name']) ? trim((string) $context['product_name']) : '';

            return [
                'knowledge_id' => null,
                'title' => $name !== '' ? $name : 'SKU '.$sku,
                'source' => 'context.product_sku',
                'external_id' => null,
            ];
        }

        return null;
    }

    /**
     * @return ProductSubject|null
     */
    public function mention(WiseApiKey $apiKey, string $text): ?array
    {
        $normalized = KnowledgeLookup::normalize($text);
        if ($normalized === '') {
            return null;
        }

        $tokens = KnowledgeLookup::tokens($normalized);
        $base = WiseKnowledgeItem::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('status', 'published')
            ->where('type', 'product');

        $items = null;
        if ($tokens !== []) {
            $items = (clone $base)
                ->where(function (Builder $q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $q->orWhere('match_text', 'like', KnowledgeLookup::likeContains($token));
                    }
                })
                ->orderByDesc('id')
                ->limit(KnowledgeLookup::CANDIDATE_LIMIT)
                ->get(['id', 'title', 'question', 'keywords', 'external_id', 'match_text']);
        }

        if ($items === null || $items->isEmpty()) {
            $items = $base
                ->orderByDesc('id')
                ->limit(KnowledgeLookup::CANDIDATE_LIMIT)
                ->get(['id', 'title', 'question', 'keywords', 'external_id', 'match_text']);
        }

        if ($items->isEmpty()) {
            return null;
        }

        $scored = [];
        foreach ($items as $item) {
            $score = $this->score($item, $normalized);
            if ($score < self::MIN_SCORE) {
                continue;
            }
            $scored[] = ['item' => $item, 'score' => $score];
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $scored[0];
        $bestScore = (int) $best['score'];
        $secondScore = isset($scored[1]) ? (int) $scored[1]['score'] : 0;

        // Ambiguous weak mentions → leave subject unset so KnowledgeResolver can shortlist (S5).
        if (
            count($scored) >= 2
            && $bestScore < 70
            && ($bestScore - $secondScore) < 12
        ) {
            return null;
        }

        $bestItem = $best['item'];

        return [
            'knowledge_id' => (int) $bestItem->id,
            'title' => (string) $bestItem->title,
            'source' => 'mention',
            'external_id' => $bestItem->external_id,
        ];
    }

    private function score(WiseKnowledgeItem $item, string $normalizedText): int
    {
        $title = KnowledgeLookup::normalize((string) $item->title);
        $question = KnowledgeLookup::normalize((string) ($item->question ?? ''));
        $keywords = KnowledgeLookup::normalize(implode(' ', $item->keywords ?? []));
        $score = 0;

        foreach ([$title, $question] as $field) {
            if ($field === '') {
                continue;
            }
            if (str_contains($normalizedText, $field) || (mb_strlen($field) >= 4 && str_contains($field, $normalizedText))) {
                $score += 70;
            }
            foreach (preg_split('/\s+/u', $field) ?: [] as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }
                if (str_contains($normalizedText, $token)) {
                    $score += 12;
                }
            }
        }

        foreach (preg_split('/\s+/u', $keywords) ?: [] as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }
            if (str_contains($normalizedText, $token)) {
                $score += 15;
            }
        }

        return $score;
    }
}
