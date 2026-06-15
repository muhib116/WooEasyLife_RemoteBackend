<?php

namespace App\Services\Courier;

use App\Models\CourierForwardRetry;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;

class CourierForwardRetryService
{
    public const RETRY_DELAYS_SECONDS = [30, 120, 300];

    public function __construct(
        protected WordPressCourierForwarder $forwarder
    ) {
    }

    public function queueRetry(CourierShipment $shipment, array $payload, ?CourierWebhookEvent $event = null, string $error = ''): void
    {
        $existing = CourierForwardRetry::query()
            ->where('shipment_id', $shipment->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return;
        }

        CourierForwardRetry::query()->create([
            'shipment_id' => $shipment->id,
            'webhook_event_id' => $event?->id,
            'payload' => $payload,
            'attempts' => 0,
            'max_attempts' => count(self::RETRY_DELAYS_SECONDS),
            'next_retry_at' => now()->addSeconds(self::RETRY_DELAYS_SECONDS[0]),
            'last_error' => substr($error, 0, 255),
            'status' => 'pending',
        ]);
    }

    public function processDueRetries(int $limit = 25): array
    {
        $rows = CourierForwardRetry::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($rows as $retry) {
            $processed++;
            $shipment = CourierShipment::query()->find($retry->shipment_id);

            if (!$shipment) {
                $retry->status = 'failed';
                $retry->last_error = 'shipment_missing';
                $retry->save();
                $failed++;
                continue;
            }

            $payload = is_array($retry->payload) ? $retry->payload : [];
            $result = $this->forwarder->forward($shipment, $payload);
            $retry->attempts = (int) $retry->attempts + 1;
            $retry->last_attempt_at = now();

            if (!empty($result['success'])) {
                $retry->status = 'completed';
                $retry->last_error = null;
                $retry->next_retry_at = null;
                $retry->save();

                $shipment->status = (string) ($payload['raw_status'] ?? $shipment->status);
                $shipment->last_webhook_at = now();
                $shipment->save();

                if ($retry->webhook_event_id) {
                    CourierWebhookEvent::query()
                        ->where('id', $retry->webhook_event_id)
                        ->update([
                            'forward_status' => 'success',
                            'forward_message' => 'forwarded_after_retry',
                        ]);
                }

                $succeeded++;
                continue;
            }

            $error = (string) ($result['message'] ?? 'forward_failed');
            $retry->last_error = substr($error, 0, 255);

            if ($retry->attempts >= (int) $retry->max_attempts) {
                $retry->status = 'failed';
                $retry->next_retry_at = null;
                $retry->save();

                if ($retry->webhook_event_id) {
                    CourierWebhookEvent::query()
                        ->where('id', $retry->webhook_event_id)
                        ->update([
                            'forward_status' => 'failed',
                            'forward_message' => $error,
                        ]);
                }

                $failed++;
                continue;
            }

            $delayIndex = min($retry->attempts, count(self::RETRY_DELAYS_SECONDS) - 1);
            $retry->next_retry_at = now()->addSeconds(self::RETRY_DELAYS_SECONDS[$delayIndex]);
            $retry->save();
            $failed++;
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    public function pendingCount(?int $accessTokenId = null): int
    {
        $query = CourierForwardRetry::query()->where('status', 'pending');

        if ($accessTokenId) {
            $query->whereHas('shipment', function ($builder) use ($accessTokenId) {
                $builder->where('access_token_id', $accessTokenId);
            });
        }

        return (int) $query->count();
    }
}
