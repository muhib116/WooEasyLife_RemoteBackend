<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Models\OrderIntelligence\OrderStatusEvent;
use App\Models\OrderIntelligence\PlatformOrder;
use Illuminate\Support\Facades\DB;

class StatusTransitionService
{
    public function apply(
        PlatformOrder $order,
        OrderStatus $toStatus,
        string $source,
        string $idempotencyKey,
        ?string $partner = null,
        ?string $rawStatus = null,
        ?int $accessTokenId = null,
        ?int $courierWebhookEventId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): bool {
        if (OrderStatusEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        $fromStatus = OrderStatus::tryFrom((string) $order->current_status);
        $occurredAt ??= now();

        if ($fromStatus !== null && ! $fromStatus->canTransitionTo($toStatus)) {
            return false;
        }

        if ($fromStatus === $toStatus) {
            return false;
        }

        DB::transaction(function () use (
            $order,
            $fromStatus,
            $toStatus,
            $source,
            $idempotencyKey,
            $partner,
            $rawStatus,
            $accessTokenId,
            $courierWebhookEventId,
            $occurredAt,
        ) {
            OrderStatusEvent::query()->create([
                'platform_order_id' => $order->id,
                'platform_customer_id' => $order->platform_customer_id,
                'from_status' => $fromStatus?->value,
                'to_status' => $toStatus->value,
                'source' => $source,
                'access_token_id' => $accessTokenId,
                'courier_webhook_event_id' => $courierWebhookEventId,
                'partner' => $partner,
                'raw_status' => $rawStatus,
                'occurred_at' => $occurredAt,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            $order->current_status = $toStatus->value;
            $order->status_changed_at = $occurredAt;
            $order->save();
        });

        return true;
    }

    public function recordInitial(
        PlatformOrder $order,
        OrderStatus $status,
        string $source,
        string $idempotencyKey,
        ?int $accessTokenId = null,
    ): bool {
        if (OrderStatusEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        OrderStatusEvent::query()->create([
            'platform_order_id' => $order->id,
            'platform_customer_id' => $order->platform_customer_id,
            'from_status' => null,
            'to_status' => $status->value,
            'source' => $source,
            'access_token_id' => $accessTokenId,
            'occurred_at' => $order->status_changed_at ?? now(),
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);

        return true;
    }
}
