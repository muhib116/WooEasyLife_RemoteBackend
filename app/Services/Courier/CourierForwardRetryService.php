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
    ) {}

    public function queueRetry(CourierShipment $shipment, array $payload, ?CourierWebhookEvent $event = null, string $error = ''): void
    {
        $existing = CourierForwardRetry::query()
            ->where('shipment_id', $shipment->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existingAt = is_array($existing->payload) ? (string) ($existing->payload['updated_at'] ?? '') : '';
            $incomingAt = (string) ($payload['updated_at'] ?? '');

            if ($this->timestampIsNewer($incomingAt, $existingAt)) {
                $existing->payload = $this->pluginPayload($payload);
                $existing->webhook_event_id = $event?->id ?? $existing->webhook_event_id;
                $existing->attempts = 0;
                $existing->next_retry_at = now()->addSeconds(self::RETRY_DELAYS_SECONDS[0]);
                $existing->last_error = $this->clip($error);
                $existing->save();
            }

            return;
        }

        CourierForwardRetry::query()->create([
            'shipment_id' => $shipment->id,
            'webhook_event_id' => $event?->id,
            'payload' => $this->pluginPayload($payload),
            'attempts' => 0,
            'max_attempts' => count(self::RETRY_DELAYS_SECONDS),
            'next_retry_at' => now()->addSeconds(self::RETRY_DELAYS_SECONDS[0]),
            'last_error' => $this->clip($error),
            'status' => 'pending',
        ]);
    }

    public function processDueRetries(int $limit = 25, ?int $stopAfterSeconds = null): array
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
        $deadline = $stopAfterSeconds !== null ? microtime(true) + max(1, $stopAfterSeconds) : null;

        foreach ($rows as $retry) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            $processed++;
            $shipment = CourierShipment::query()->find($retry->shipment_id);

            if (! $shipment) {
                $retry->status = 'failed';
                $retry->last_error = 'shipment_missing';
                $retry->save();
                $failed++;

                continue;
            }

            $payload = $this->pluginPayload(is_array($retry->payload) ? $retry->payload : []);
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
                            'forward_message' => 'forwarded_after_retry',
                        ]);
                }

                $succeeded++;

                continue;
            }

            $error = (string) ($result['message'] ?? 'forward_failed');
            $retry->last_error = $this->clip($error);

            if ($retry->attempts >= (int) $retry->max_attempts) {
                $retry->status = 'failed';
                $retry->next_retry_at = null;
                $retry->save();

                if ($retry->webhook_event_id) {
                    CourierWebhookEvent::query()
                        ->where('id', $retry->webhook_event_id)
                        ->update([
                            'forward_status' => 'failed',
                            'forward_message' => $this->clip($error),
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

    private function timestampIsNewer(string $incoming, string $existing): bool
    {
        if ($incoming === '') {
            return false;
        }

        if ($existing === '') {
            return true;
        }

        $incomingTs = strtotime($incoming);
        $existingTs = strtotime($existing);

        if ($incomingTs !== false && $existingTs !== false) {
            return $incomingTs > $existingTs;
        }

        return $incoming > $existing;
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

    /**
     * Make every queued forward for one store due now. Recently exhausted rows
     * are revived too, because they only failed while the signing key was wrong.
     */
    public function releaseForAccessToken(int $accessTokenId): int
    {
        if ($accessTokenId <= 0) {
            return 0;
        }

        $scope = function ($query) use ($accessTokenId) {
            $query->where('access_token_id', $accessTokenId);
        };

        $released = CourierForwardRetry::query()
            ->where('status', 'pending')
            ->whereHas('shipment', $scope)
            ->update(['next_retry_at' => now()]);

        $revived = CourierForwardRetry::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereIn('last_error', ['auth_rejected', 'forward_failed', 'forward_exception'])
            ->whereHas('shipment', $scope)
            ->update([
                'status' => 'pending',
                'attempts' => 0,
                'next_retry_at' => now(),
            ]);

        return (int) $released + (int) $revived;
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

        if (in_array($event->forward_status, ['queued', 'forwarding'], true)) {
            return ['success' => false, 'message' => 'already_in_progress'];
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

        $summary = is_array($event->payload_summary) ? $event->payload_summary : [];
        $stored = is_array($summary['forward_payload'] ?? null) ? $summary['forward_payload'] : $summary;
        $forwardPayload = array_merge([
            'partner' => $event->partner,
            'raw_status' => $event->event_type ?? 'webhook',
            'updated_at' => (string) ($summary['updated_at'] ?? now()->toDateTimeString()),
            'tracking_message' => '',
        ], $stored, [
            'order_id' => (int) $shipment->wc_order_id,
            'courier_account_id' => (int) $shipment->courier_account_id,
        ]);
        unset($forwardPayload['forward_payload'], $forwardPayload['consignment_id']);
        $forwardPayload = $this->pluginPayload($forwardPayload);

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
        $event->forward_message = $this->clip($error);
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

        $payload = $this->pluginPayload(is_array($retry->payload) ? $retry->payload : []);
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
        $retry->last_error = $this->clip($error);

        if ($retry->attempts >= (int) $retry->max_attempts) {
            $retry->status = 'failed';
            $retry->next_retry_at = null;
            $retry->save();

            if ($retry->webhook_event_id) {
                CourierWebhookEvent::query()
                    ->where('id', $retry->webhook_event_id)
                    ->update([
                        'forward_status' => 'failed',
                        'forward_message' => $this->clip($error),
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
                    'forward_message' => $this->clip($error),
                ]);
        }

        return ['success' => false, 'message' => $error];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pluginPayload(array $payload): array
    {
        unset($payload['consignment_id'], $payload['forward_payload']);

        return $payload;
    }

    private function clip(string $message): string
    {
        return mb_substr($message, 0, 255);
    }
}
