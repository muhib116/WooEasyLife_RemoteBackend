<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Jobs\OrderIntelligence\ProjectCustomerStatsJob;
use App\Models\CourierShipment;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformOrder;
use Illuminate\Support\Facades\Log;

class CourierEntryIngestor
{
    public function __construct(
        private StatusTransitionService $statusTransitionService,
        private IntelligenceCache $cache,
    ) {}

    public function ingestFromShipment(CourierShipment $shipment): void
    {
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

            $changed = $this->statusTransitionService->apply(
                order: $order,
                toStatus: OrderStatus::CourierEntry,
                source: 'courier_create',
                idempotencyKey: sprintf(
                    'courier_create:%s:%s',
                    $shipment->partner,
                    $shipment->consignment_id,
                ),
                partner: $shipment->partner,
                rawStatus: 'courier_entry',
                accessTokenId: (int) $shipment->access_token_id,
            );

            $order->courier_partner = $shipment->partner;
            $order->consignment_id = $shipment->consignment_id;
            $order->courier_shipment_id = $shipment->id;
            $order->save();

            if (! $changed) {
                return;
            }

            $phone = PlatformCustomer::query()
                ->where('id', $order->platform_customer_id)
                ->value('phone_normalized');

            if ($phone) {
                $this->cache->forget($phone, (int) $shipment->access_token_id);
            }

            ProjectCustomerStatsJob::dispatch($order->platform_customer_id);
        } catch (\Throwable $exception) {
            Log::warning('Order intelligence courier entry ingest failed.', [
                'shipment_id' => $shipment->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
