<?php

namespace App\Services\Courier;

use App\Models\CourierConfiguration;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class SteadfastStatusBatchService
{
    private const DEFAULT_BASE_URL = 'https://portal.packzy.com/api/v1';

    public function __construct(
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
    }

    /**
     * Fetch delivery statuses for consignment IDs and invoice IDs in parallel.
     *
     * @param  array<int|string>  $consignmentIds
     * @param  array<int|string>  $invoiceIds
     * @return array<string, string>
     */
    public function fetchStatuses(
        CourierConfiguration $config,
        array $consignmentIds,
        array $invoiceIds,
        ?int $concurrency = null,
    ): array {
        $consignmentIds = $this->normalizeKeys($consignmentIds);
        $invoiceIds = $this->normalizeKeys($invoiceIds);

        if ($consignmentIds === [] && $invoiceIds === []) {
            return [];
        }

        $limit = max(1, min(50, $concurrency ?? (int) config('courier.bulk_status_concurrency', 15)));
        $responses = [];

        foreach (array_chunk($consignmentIds, $limit) as $chunk) {
            $responses += $this->fetchConsignmentStatuses($config, $chunk);
        }

        foreach (array_chunk($invoiceIds, $limit) as $chunk) {
            $responses += $this->fetchInvoiceStatuses($config, $chunk);
        }

        return $responses;
    }

    /**
     * @param  array<int, string>  $consignmentIds
     * @return array<string, string>
     */
    private function fetchConsignmentStatuses(
        CourierConfiguration $config,
        array $consignmentIds,
    ): array {
        if ($consignmentIds === []) {
            return [];
        }

        $headers = $this->requestHeaders($config);
        $responses = Http::pool(function (Pool $pool) use ($consignmentIds, $headers) {
            foreach ($consignmentIds as $id) {
                $pool->as($id)
                    ->withHeaders($headers)
                    ->timeout(12)
                    ->get($this->baseUrl . '/status_by_cid/' . rawurlencode($id));
            }
        });

        return $this->mapResponses($consignmentIds, $responses);
    }

    /**
     * @param  array<int, string>  $invoiceIds
     * @return array<string, string>
     */
    private function fetchInvoiceStatuses(
        CourierConfiguration $config,
        array $invoiceIds,
    ): array {
        if ($invoiceIds === []) {
            return [];
        }

        $headers = $this->requestHeaders($config);
        $responses = Http::pool(function (Pool $pool) use ($invoiceIds, $headers) {
            foreach ($invoiceIds as $id) {
                $pool->as($id)
                    ->withHeaders($headers)
                    ->timeout(12)
                    ->get($this->baseUrl . '/status_by_invoice/' . rawurlencode($id));
            }
        });

        return $this->mapResponses($invoiceIds, $responses);
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $responses
     * @return array<string, string>
     */
    private function mapResponses(array $keys, array $responses): array
    {
        $mapped = [];

        foreach ($keys as $key) {
            $mapped[$key] = $this->extractDeliveryStatus($responses[$key] ?? null);
        }

        return $mapped;
    }

    private function extractDeliveryStatus(mixed $response): string
    {
        if ($response === null) {
            return '';
        }

        try {
            if (method_exists($response, 'successful') && !$response->successful()) {
                return '';
            }

            $jsonResponse = $response->json();
            if (@$jsonResponse['status'] == '200') {
                return (string) (@$jsonResponse['delivery_status'] ?? '');
            }
        } catch (\Throwable $th) {
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(CourierConfiguration $config): array
    {
        return [
            'Api-Key' => (string) $config->api_key,
            'Secret-Key' => (string) $config->secret_key,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param  array<int|string>  $values
     * @return array<int, string>
     */
    private function normalizeKeys(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $key = trim((string) $value);
            if ($key !== '') {
                $normalized[] = $key;
            }
        }

        return $normalized;
    }
}
