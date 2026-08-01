<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use Illuminate\Support\Collection;

/**
 * Ground business answers on published merchant knowledge (single source of truth).
 *
 * Matching is intentionally strict to avoid cross-intent false positives
 * (e.g. a delivery FAQ answering an order-status question).
 */
class KnowledgeResolver
{
    /** Minimum lexical score (title/question/keywords only) before type boost. */
    private const MIN_LEXICAL = 30;

    /** Final score threshold after boosts. */
    private const MIN_FINAL = 40;

    /**
     * Tokens that should appear in the knowledge item for a given intent
     * (unless there is a very strong substring match on question/title).
     *
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
    public function resolve(WiseApiKey $apiKey, string $text, string $intent): ?array
    {
        $normalized = $this->normalize($text);

        /** @var Collection<int, WiseKnowledgeItem> $items */
        $items = WiseKnowledgeItem::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('status', 'published')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            $score = $this->scoreItem($item, $normalized, $intent);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if (! $best || $bestScore < self::MIN_FINAL) {
            return null;
        }

        return ['item' => $best, 'score' => $bestScore];
    }

    private function scoreItem(WiseKnowledgeItem $item, string $normalizedText, string $intent): int
    {
        $title = $this->normalize((string) $item->title);
        $question = $this->normalize((string) ($item->question ?? ''));
        $keywords = $this->normalize(implode(' ', $item->keywords ?? []));
        $haystack = trim($title.' '.$question.' '.$keywords);

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

        // Intent gate: knowledge should be about this intent (unless strong Q/title match).
        $hints = self::INTENT_HINTS[$intent] ?? null;
        if ($hints !== null && ! $strongSubstring && ! $this->haystackHasHint($haystack, $hints)) {
            return 0;
        }

        $score = $lexical;

        if ($item->type === 'faq' && in_array($intent, DecideEngine::BUSINESS_INTENTS, true)) {
            $score += 5;
        }
        if ($item->type === 'policy' && in_array($intent, ['complaint', 'delivery'], true)) {
            $score += 8;
        }
        if ($item->type === 'product' && $intent === 'price') {
            $score += 8;
        }

        return $score;
    }

    /**
     * @param  list<string>  $hints
     */
    private function haystackHasHint(string $haystack, array $hints): bool
    {
        foreach ($hints as $hint) {
            $hint = $this->normalize($hint);
            if ($hint !== '' && str_contains($haystack, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return (string) preg_replace('/\s+/u', ' ', $text);
    }
}
