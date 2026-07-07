<?php

namespace App\Services\OrderIntelligence;

use App\Jobs\OrderIntelligence\ProjectCustomerStatsJob;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformOrder;
use Illuminate\Support\Facades\Log;

class WebhookOrderIngestor
{
    public function __construct(
        private CourierStatusMapper $statusMapper,
        private StatusTransitionService $statusTransitionService,
        private IntelligenceCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(
        CourierShipment $shipment,
        array $payload,
        ?CourierWebhookEvent $webhookEvent = null,
    ): void {
        if (! config('order_intelligence.enabled', true)) {
            return;
        }

        if (! $shipment->wc_order_id || ! $shipment->access_token_id) {
            return;
        }

        try {
            $order = PlatformOrder::query()
                ->where('access_token_id', $shipment->access_token_id)
                ->where('wc_order_id', $shipment->wc_order_id)
                ->first();

            if (! $order) {
                return;
            }

            $rawStatus = (string) ($payload['raw_status'] ?? ($payload['notification_type'] ?? ''));
            $mappedStatus = $this->statusMapper->map($shipment->partner, $rawStatus);

            if ($mappedStatus === null) {
                return;
            }

            $eventId = $webhookEvent?->id;
            $idempotencyKey = sprintf(
                'webhook:%s:%s:%s:%s:%s',
                $shipment->partner,
                $shipment->consignment_id,
                $rawStatus,
                (string) ($payload['updated_at'] ?? now()->toDateTimeString()),
                (string) $eventId,
            );

            $changed = $this->statusTransitionService->apply(
                order: $order,
                toStatus: $mappedStatus,
                source: 'webhook',
                idempotencyKey: $idempotencyKey,
                partner: $shipment->partner,
                rawStatus: $rawStatus,
                accessTokenId: (int) $shipment->access_token_id,
                courierWebhookEventId: $eventId,
                occurredAt: isset($payload['updated_at']) ? now()->parse($payload['updated_at']) : now(),
            );

            if (! $changed) {
                return;
            }

            $order->courier_partner = $shipment->partner;
            $order->consignment_id = $shipment->consignment_id;
            $order->courier_shipment_id = $shipment->id;
            $order->save();

            $phone = PlatformCustomer::query()
                ->where('id', $order->platform_customer_id)
                ->value('phone_normalized');

            if ($phone) {
                $this->cache->forget($phone, (int) $shipment->access_token_id);
            }

            ProjectCustomerStatsJob::dispatch($order->platform_customer_id);
        } catch (\Throwable $exception) {
            Log::warning('Order intelligence webhook ingest failed.', [
                'shipment_id' => $shipment->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
