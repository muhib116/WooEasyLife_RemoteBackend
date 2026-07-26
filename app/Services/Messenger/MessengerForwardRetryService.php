<?php

namespace App\Services\Messenger;

use App\Models\MessengerForwardRetry;
use App\Models\MessengerPageConnection;

class MessengerForwardRetryService
{
    public const RETRY_DELAYS_SECONDS = [30, 120, 300];

    public function __construct(
        protected WordPressMessengerForwarder $forwarder,
        protected MessengerInboundEnricher $enricher
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function fingerprint(array $events): string
    {
        $keys = [];
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            $keys[] = implode(':', [
                (string) ($event['page_id'] ?? ''),
                (string) ($event['psid'] ?? ''),
                (string) ($event['event_type'] ?? 'message'),
                (string) ($event['message']['mid'] ?? ($event['reaction']['mid'] ?? '')),
                (string) ($event['reaction']['action'] ?? ''),
            ]);
        }
        sort($keys);

        return sha1(implode('|', $keys));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{success: bool, message: string, http_status?: int, site_url?: string}
     */
    public function forwardNow(MessengerPageConnection $connection, array $events, bool $enrich = true): array
    {
        $payloadEvents = $enrich ? $this->enricher->enrich($connection, $events) : $events;

        return $this->forwarder->forwardInbound($connection, [
            'events' => $payloadEvents,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function queueRetry(MessengerPageConnection $connection, array $events, string $error = ''): void
    {
        $fingerprint = $this->fingerprint($events);

        $existing = MessengerForwardRetry::query()
            ->where('messenger_page_connection_id', $connection->id)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existing->last_error = substr($error, 0, 255);
            $existing->payload = ['events' => $events];
            if ($existing->next_retry_at === null || $existing->next_retry_at->isPast()) {
                $existing->next_retry_at = now()->addSeconds(self::RETRY_DELAYS_SECONDS[0]);
            }
            $existing->save();

            return;
        }

        MessengerForwardRetry::query()->create([
            'messenger_page_connection_id' => $connection->id,
            'page_id' => (string) $connection->page_id,
            'fingerprint' => $fingerprint,
            'payload' => ['events' => $events],
            'attempts' => 0,
            'max_attempts' => count(self::RETRY_DELAYS_SECONDS),
            'next_retry_at' => now()->addSeconds(self::RETRY_DELAYS_SECONDS[0]),
            'last_error' => substr($error, 0, 255),
            'status' => 'pending',
        ]);
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function processDueRetries(int $limit = 25): array
    {
        $rows = MessengerForwardRetry::query()
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
            $result = $this->executeRetry($retry);
            if (! empty($result['success'])) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function executeRetry(MessengerForwardRetry $retry): array
    {
        $connection = MessengerPageConnection::query()->find($retry->messenger_page_connection_id);
        if (! $connection || $connection->status !== 'connected') {
            $retry->status = 'failed';
            $retry->last_error = 'connection_missing';
            $retry->next_retry_at = null;
            $retry->save();

            return ['success' => false, 'message' => 'connection_missing'];
        }

        $payload = is_array($retry->payload) ? $retry->payload : [];
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];
        if ($events === []) {
            $retry->status = 'failed';
            $retry->last_error = 'empty_payload';
            $retry->next_retry_at = null;
            $retry->save();

            return ['success' => false, 'message' => 'empty_payload'];
        }

        $result = $this->forwardNow($connection, $events, true);
        $retry->attempts = (int) $retry->attempts + 1;
        $retry->last_attempt_at = now();

        if (! empty($result['success'])) {
            $retry->status = 'completed';
            $retry->last_error = null;
            $retry->next_retry_at = null;
            $retry->save();

            return ['success' => true, 'message' => 'forwarded'];
        }

        $error = (string) ($result['message'] ?? 'forward_failed');
        $retry->last_error = substr($error, 0, 255);

        if ($retry->attempts >= (int) $retry->max_attempts) {
            $retry->status = 'failed';
            $retry->next_retry_at = null;
            $retry->save();

            return ['success' => false, 'message' => $error];
        }

        $delayIndex = min(max(0, $retry->attempts - 1), count(self::RETRY_DELAYS_SECONDS) - 1);
        $retry->next_retry_at = now()->addSeconds(self::RETRY_DELAYS_SECONDS[$delayIndex]);
        $retry->save();

        return ['success' => false, 'message' => $error];
    }
}
