<?php

namespace App\Services\BlogAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live Bangladesh Google Suggest enrichment (gl=bd).
 */
class BdKeywordSuggestService
{
    /**
     * @return list<string>
     */
    public function suggest(string $seed, int $limit = 12): array
    {
        $seed = trim($seed);
        if ($seed === '') {
            return [];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'WooEasyLife-BlogAI/1.0',
                    'Accept-Language' => 'bn-BD,bn;q=0.9,en;q=0.8',
                ])
                ->get('https://suggestqueries.google.com/complete/search', [
                    'client' => 'firefox',
                    'hl' => 'bn',
                    'gl' => 'bd',
                    'q' => $seed,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();
            $suggestions = is_array($json[1] ?? null) ? $json[1] : [];

            return collect($suggestions)
                ->map(fn ($row) => trim((string) $row))
                ->filter()
                ->unique()
                ->take($limit)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::debug('BD keyword suggest failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  list<string>  $seeds
     * @return list<string>
     */
    public function suggestMany(array $seeds, int $perSeed = 6, int $maxTotal = 24): array
    {
        $out = [];
        foreach (array_slice($seeds, 0, 5) as $seed) {
            foreach ($this->suggest($seed, $perSeed) as $suggestion) {
                $out[] = $suggestion;
            }
        }

        return collect($out)->unique()->take($maxTotal)->values()->all();
    }
}
