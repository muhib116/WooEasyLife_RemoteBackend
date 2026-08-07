<?php

namespace App\WiseAi\Assist;

/**
 * Detect contradictory numeric claims across evidence chunks (e.g. FAQ vs product).
 * Does not invent a merged price — caller should clarify or handoff.
 */
final class ContradictionDetector
{
    /**
     * @param  list<array<string, mixed>>  $chunks
     * @return list<array{field: string, values: list<string>, knowledge_ids: list<int>}>
     */
    public function find(array $chunks): array
    {
        $priceById = [];
        foreach ($chunks as $chunk) {
            $id = (int) ($chunk['id'] ?? 0);
            $blob = trim((string) ($chunk['title'] ?? '').' '.(string) ($chunk['answer'] ?? ''));
            foreach ($this->priceLikeTokens($blob) as $token) {
                $priceById[$id][] = $token;
            }
        }

        $unique = [];
        foreach ($priceById as $id => $tokens) {
            foreach (array_unique($tokens) as $token) {
                $unique[$token][] = $id;
            }
        }

        if (count($unique) < 2) {
            return [];
        }

        // Only flag when distinct price-like values appear across different knowledge rows.
        $values = array_keys($unique);
        $ids = [];
        foreach ($unique as $idList) {
            foreach ($idList as $id) {
                $ids[$id] = true;
            }
        }
        if (count($ids) < 2) {
            return [];
        }

        return [[
            'field' => 'price_like',
            'values' => array_slice($values, 0, 6),
            'knowledge_ids' => array_map('intval', array_keys($ids)),
        ]];
    }

    /**
     * @return list<string>
     */
    private function priceLikeTokens(string $text): array
    {
        $normalized = str_replace(
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text,
        );
        preg_match_all('/(?:tk|৳|bdt)?\s*(\d{2,7})(?:\s*(?:tk|৳|taka|টাকা))?/iu', $normalized, $m);
        $out = [];
        foreach ($m[1] ?? [] as $n) {
            $out[] = (string) $n;
        }

        return array_values(array_unique($out));
    }
}
