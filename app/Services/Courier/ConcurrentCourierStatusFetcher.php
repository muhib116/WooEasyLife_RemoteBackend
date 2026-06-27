<?php

namespace App\Services\Courier;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

/**
 * Runs courier status HTTP lookups with bounded concurrency.
 */
class ConcurrentCourierStatusFetcher
{
    public static function defaultConcurrency(): int
    {
        $configured = config('courier.bulk_status_concurrency');
        $value = is_numeric($configured)
            ? (int) $configured
            : (int) env('COURIER_BULK_STATUS_CONCURRENCY', 15);

        return max(1, min(25, $value));
    }

    /**
     * @param array<int|string, string> $ids
     * @param callable(Pool $pool, string $id): void $registerRequest
     * @param callable(\Illuminate\Http\Client\Response|null $response): string $parseResponse
     * @return array<string, string>
     */
    public static function fetchStatusMap(
        array $ids,
        callable $registerRequest,
        callable $parseResponse
    ): array {
        $normalized = [];

        foreach ($ids as $id) {
            $key = trim((string) $id);
            if ($key !== '') {
                $normalized[$key] = $key;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $results = [];
        $concurrency = self::defaultConcurrency();

        foreach (array_chunk(array_values($normalized), $concurrency) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $registerRequest) {
                foreach ($chunk as $id) {
                    $registerRequest($pool, $id);
                }
            });

            foreach ($chunk as $id) {
                $response = is_array($responses) ? ($responses[$id] ?? null) : null;

                try {
                    $results[$id] = $parseResponse($response);
                } catch (\Throwable $th) {
                    $results[$id] = '';
                }
            }
        }

        return $results;
    }
}
