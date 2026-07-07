<?php

namespace App\Services\Courier;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\CourierShipment;
use App\Services\OrderIntelligence\CourierEntryIngestor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourierShipmentService
{
    public function __construct(
        protected CourierAccountService $accountService,
        protected CourierEntryIngestor $courierEntryIngestor,
    ) {
    }

    public function normalizeEnvironment(?string $environment): string
    {
        return $environment === 'sandbox' ? 'sandbox' : 'live';
    }

    public function recordSuccessfulOrders(
        string $partner,
        CourierConfiguration $configuration,
        array $orders,
        Request $request,
        ?int $courierAccountId = null
    ): array {
        $partner = strtolower(trim($partner));
        $accessToken = $this->accountService->resolveAccessToken($request);
        $siteUrl = $this->accountService->resolveSiteUrl($request);
        $siteDomain = $accessToken?->domain ?: parse_url($siteUrl, PHP_URL_HOST);
        $environment = $this->accountService->environmentFromConfig($configuration);
        $accountId = $courierAccountId;

        if (!$accountId) {
            $sync = $this->accountService->syncAccountForConfiguration($configuration, $request);
            $accountId = (int) ($sync['courier_account_id'] ?? 0);
        }

        $enriched = [];

        foreach ($orders as $order) {
            if (!is_array($order) || !empty($order['error'])) {
                $enriched[] = $order;
                continue;
            }

            $consignmentId = trim((string) ($order['consignment_id'] ?? $order['tracking_code'] ?? ''));
            if ($consignmentId === '' || $consignmentId === 'not-available') {
                $enriched[] = $order;
                continue;
            }

            $invoice = trim((string) ($order['invoice'] ?? ''));
            $wcOrderId = $this->accountService->parseInvoiceOrderId($invoice);

            if ($accessToken && $accountId) {
                try {
                    $shipment = CourierShipment::query()->updateOrCreate(
                        [
                            'partner' => $partner,
                            'consignment_id' => $consignmentId,
                            'environment' => $environment,
                        ],
                        [
                            'invoice' => $invoice,
                            'wc_order_id' => $wcOrderId > 0 ? $wcOrderId : null,
                            'user_id' => (int) ($configuration->user_id ?? 0),
                            'access_token_id' => $accessToken->id,
                            'site_url' => $siteUrl,
                            'site_domain' => (string) $siteDomain,
                            'courier_account_id' => $accountId,
                            'courier_configuration_id' => $configuration->id,
                            'status' => (string) ($order['status'] ?? 'pending'),
                        ]
                    );

                    $this->courierEntryIngestor->ingestFromShipment($shipment);
                } catch (\Throwable $exception) {
                    Log::warning('Courier shipment mapping failed; order create still succeeded.', [
                        'partner' => $partner,
                        'consignment_id' => $consignmentId,
                        'environment' => $environment,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $order['courier_account_id'] = $accountId;
            $enriched[] = $order;
        }

        return $enriched;
    }

    public function findByConsignment(string $partner, string $consignmentId, ?string $environment = null): ?CourierShipment
    {
        $partner = strtolower(trim($partner));
        $consignmentId = trim($consignmentId);

        if ($partner === '' || $consignmentId === '') {
            return null;
        }

        $query = CourierShipment::query()
            ->where('partner', $partner)
            ->where(function ($builder) use ($consignmentId) {
                $builder->where('consignment_id', $consignmentId);

                if (ctype_digit($consignmentId)) {
                    $builder->orWhere('consignment_id', (string) ((int) $consignmentId));
                }
            });

        if ($environment !== null) {
            $query->where('environment', $this->normalizeEnvironment($environment));
        }

        return $query->orderByDesc('id')->first();
    }

    public function findByInvoice(string $partner, string $invoice, ?string $environment = null): ?CourierShipment
    {
        $partner = strtolower(trim($partner));
        $invoice = trim($invoice);

        if ($partner === '' || $invoice === '') {
            return null;
        }

        $query = CourierShipment::query()
            ->where('partner', $partner)
            ->where('invoice', $invoice);

        if ($environment !== null) {
            $query->where('environment', $this->normalizeEnvironment($environment));
        }

        $shipment = $query->orderByDesc('id')->first();
        if ($shipment) {
            return $shipment;
        }

        $wcOrderId = $this->accountService->parseInvoiceOrderId($invoice);
        if ($wcOrderId <= 0) {
            return null;
        }

        $orderQuery = CourierShipment::query()
            ->where('partner', $partner)
            ->where('wc_order_id', $wcOrderId);

        if ($environment !== null) {
            $orderQuery->where('environment', $this->normalizeEnvironment($environment));
        }

        return $orderQuery->orderByDesc('id')->first();
    }

    public function resolveShipment(
        string $partner,
        string $consignmentId,
        string $invoice = '',
        ?string $environment = null
    ): ?CourierShipment {
        $shipment = $this->findByConsignment($partner, $consignmentId, $environment);

        if ($shipment || trim($invoice) === '') {
            return $shipment;
        }

        return $this->findByInvoice($partner, $invoice, $environment);
    }

    public function groupConsignmentsByAccount(string $partner, array $consignmentIds, ?string $environment = null): array
    {
        $partner = strtolower(trim($partner));
        $groups = [];

        foreach ($consignmentIds as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            $shipment = $this->findByConsignment($partner, $id, $environment);
            $accountId = $shipment?->courier_account_id ?? 0;
            $groups[$accountId][] = $id;
        }

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $shipments
     * @return array{created:int, updated:int, skipped:int, total:int}
     */
    public function backfillShipments(string $partner, CourierConfiguration $configuration, Request $request, array $shipments): array
    {
        $partner = strtolower(trim($partner));
        $accessToken = $this->accountService->resolveAccessToken($request);
        $siteUrl = $this->accountService->resolveSiteUrl($request);
        $siteDomain = $accessToken?->domain ?: parse_url($siteUrl, PHP_URL_HOST);
        $environment = $this->accountService->environmentFromConfig($configuration);

        $sync = $this->accountService->syncAccountForConfiguration($configuration, $request);
        $accountId = (int) ($sync['courier_account_id'] ?? 0);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        if (!$accessToken || !$accountId) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => count($shipments),
                'total' => count($shipments),
            ];
        }

        foreach ($shipments as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $consignmentId = trim((string) ($row['consignment_id'] ?? $row['tracking_code'] ?? ''));
            if ($consignmentId === '' || $consignmentId === 'not-available') {
                $skipped++;
                continue;
            }

            $invoice = trim((string) ($row['invoice'] ?? ''));
            $wcOrderId = (int) ($row['wc_order_id'] ?? 0);
            if ($wcOrderId <= 0) {
                $wcOrderId = $this->accountService->parseInvoiceOrderId($invoice);
            }

            $existing = $this->findByConsignment($partner, $consignmentId, $environment);
            $wasExisting = $existing !== null;

            try {
                CourierShipment::query()->updateOrCreate(
                    [
                        'partner' => $partner,
                        'consignment_id' => $consignmentId,
                        'environment' => $environment,
                    ],
                    [
                        'invoice' => $invoice,
                        'wc_order_id' => $wcOrderId > 0 ? $wcOrderId : null,
                        'user_id' => (int) ($configuration->user_id ?? 0),
                        'access_token_id' => $accessToken->id,
                        'site_url' => $siteUrl,
                        'site_domain' => (string) $siteDomain,
                        'courier_account_id' => $accountId,
                        'courier_configuration_id' => $configuration->id,
                        'status' => (string) ($row['status'] ?? ($existing?->status ?? 'pending')),
                    ]
                );

                if ($wasExisting) {
                    $updated++;
                } else {
                    $created++;
                }
            } catch (\Throwable $exception) {
                Log::warning('Courier shipment backfill row failed.', [
                    'partner' => $partner,
                    'consignment_id' => $consignmentId,
                    'message' => $exception->getMessage(),
                ]);
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($shipments),
        ];
    }

    public function latestForAccessToken(?int $accessTokenId, ?string $partner = null): ?CourierShipment
    {
        if (!$accessTokenId) {
            return null;
        }

        $query = CourierShipment::query()
            ->where('access_token_id', $accessTokenId)
            ->orderByDesc('id');

        if ($partner) {
            $query->where('partner', strtolower(trim($partner)));
        }

        return $query->first();
    }
}
