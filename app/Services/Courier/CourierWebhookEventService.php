<?php

namespace App\Services\Courier;

use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;

class CourierWebhookEventService
{
    public function record(
        string $partner,
        string $environment,
        array $context = []
    ): CourierWebhookEvent {
        return CourierWebhookEvent::query()->create([
            'partner' => strtolower(trim($partner)),
            'environment' => $environment === 'sandbox' ? 'sandbox' : 'live',
            'consignment_id' => $context['consignment_id'] ?? null,
            'shipment_id' => $context['shipment_id'] ?? null,
            'access_token_id' => $context['access_token_id'] ?? null,
            'site_url' => $context['site_url'] ?? null,
            'wc_order_id' => $context['wc_order_id'] ?? null,
            'event_type' => $context['event_type'] ?? null,
            'forward_status' => $context['forward_status'] ?? 'received',
            'forward_message' => $context['forward_message'] ?? null,
            'payload_summary' => $context['payload_summary'] ?? null,
        ]);
    }

    public function healthSummary(?int $accessTokenId = null, ?string $partner = null): array
    {
        $base = CourierWebhookEvent::query()->orderByDesc('id');

        if ($accessTokenId) {
            $base->where('access_token_id', $accessTokenId);
        }

        if ($partner) {
            $base->where('partner', strtolower(trim($partner)));
        }

        $lastReceived = (clone $base)->first();
        $lastSuccess = (clone $base)->where('forward_status', 'success')->first();
        $lastFailure = (clone $base)->whereIn('forward_status', ['failed', 'retry_queued'])->first();
        $lastOrphan = (clone $base)->where('forward_status', 'orphan')->first();

        return [
            'last_hub_webhook_at' => $lastReceived?->created_at?->toDateTimeString(),
            'last_forward_success_at' => $lastSuccess?->created_at?->toDateTimeString(),
            'last_forward_failure_at' => $lastFailure?->created_at?->toDateTimeString(),
            'last_orphan_at' => $lastOrphan?->created_at?->toDateTimeString(),
            'last_consignment_id' => $lastReceived?->consignment_id,
            'last_forward_status' => $lastReceived?->forward_status,
            'last_forward_message' => $lastReceived?->forward_message,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentEvents(?int $accessTokenId = null, ?string $partner = null, int $limit = 20): array
    {
        $query = CourierWebhookEvent::query()->orderByDesc('id');

        if ($accessTokenId) {
            $query->where('access_token_id', $accessTokenId);
        }

        if ($partner) {
            $query->where('partner', strtolower(trim($partner)));
        }

        return $query
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->map(function (CourierWebhookEvent $event) {
                return [
                    'id' => $event->id,
                    'partner' => $event->partner,
                    'environment' => $event->environment,
                    'consignment_id' => $event->consignment_id,
                    'wc_order_id' => $event->wc_order_id,
                    'event_type' => $event->event_type,
                    'forward_status' => $event->forward_status,
                    'forward_message' => $event->forward_message,
                    'created_at' => $event->created_at?->toDateTimeString(),
                ];
            })
            ->all();
    }
}
