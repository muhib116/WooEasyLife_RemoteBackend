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

    public function retryNow(CourierForwardRetry $retry): array
    {
        if ($retry->status === 'completed') {
            return ['success' => false, 'message' => 'already_completed'];
        }

        $retry->status = 'pending';
        $retry->next_retry_at = now();
        $retry->save();

        return $this->executeRetry($retry);
    }

    public function retryFromEvent(CourierWebhookEvent $event): array
    {
        $shipment = $event->shipment;

        if (! $shipment || ! $shipment->wc_order_id) {
            return ['success' => false, 'message' => 'no_shipment_mapping'];
        }

        $existingRetry = CourierForwardRetry::query()
            ->where('webhook_event_id', $event->id)
            ->whereIn('status', ['pending', 'failed'])
            ->orderByDesc('id')
            ->first();

        if ($existingRetry) {
            $existingRetry->status = 'pending';
            $existingRetry->next_retry_at = now();
            $existingRetry->save();

            return $this->executeRetry($existingRetry);
        }

        $payload = is_array($event->payload_summary) ? $event->payload_summary : [];
        $forwardPayload = array_merge([
            'partner' => $event->partner,
            'raw_status' => $event->event_type ?? 'webhook',
            'updated_at' => now()->toDateTimeString(),
            'tracking_message' => '',
        ], $payload, [
            'order_id' => (int) $shipment->wc_order_id,
            'courier_account_id' => (int) $shipment->courier_account_id,
        ]);

        $result = $this->forwarder->forward($shipment, $forwardPayload);

        if (! empty($result['success'])) {
            $shipment->status = (string) ($forwardPayload['raw_status'] ?? $shipment->status);
            $shipment->last_webhook_at = now();
            $shipment->save();

            $event->forward_status = 'success';
            $event->forward_message = 'manual_retry_success';
            $event->save();

            return ['success' => true, 'message' => 'forwarded'];
        }

        $error = (string) ($result['message'] ?? 'forward_failed');
        $event->forward_status = 'retry_queued';
        $event->forward_message = $error;
        $event->save();

        $this->queueRetry($shipment, $forwardPayload, $event, $error);

        return ['success' => false, 'message' => $error];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function executeRetry(CourierForwardRetry $retry): array
    {
        $shipment = CourierShipment::query()->find($retry->shipment_id);

        if (! $shipment) {
            $retry->status = 'failed';
            $retry->last_error = 'shipment_missing';
            $retry->save();

            return ['success' => false, 'message' => 'shipment_missing'];
        }

        $payload = is_array($retry->payload) ? $retry->payload : [];
        $result = $this->forwarder->forward($shipment, $payload);
        $retry->attempts = (int) $retry->attempts + 1;
        $retry->last_attempt_at = now();

        if (! empty($result['success'])) {
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
                        'forward_message' => 'manual_retry_success',
                    ]);
            }

            return ['success' => true, 'message' => 'forwarded'];
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

            return ['success' => false, 'message' => $error];
        }

        $delayIndex = min($retry->attempts, count(self::RETRY_DELAYS_SECONDS) - 1);
        $retry->next_retry_at = now()->addSeconds(self::RETRY_DELAYS_SECONDS[$delayIndex]);
        $retry->save();

        if ($retry->webhook_event_id) {
            CourierWebhookEvent::query()
                ->where('id', $retry->webhook_event_id)
                ->update([
                    'forward_status' => 'retry_queued',
                    'forward_message' => $error,
                ]);
        }

        return ['success' => false, 'message' => $error];
    }
}
