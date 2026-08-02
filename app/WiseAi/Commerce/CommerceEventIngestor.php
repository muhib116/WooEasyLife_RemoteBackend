<?php

namespace App\WiseAi\Commerce;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseCommerceEvent;
use App\Models\WiseAi\WiseTurn;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Idempotent commerce event ingest — adapters POST; brain stays store-agnostic.
 */
class CommerceEventIngestor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: WiseCommerceEvent, created: bool}
     */
    public function ingest(WiseApiKey $apiKey, array $payload): array
    {
        $type = (string) ($payload['event_type'] ?? '');
        if (! CommerceEventTypes::isValid($type)) {
            throw new InvalidArgumentException('Invalid event_type.');
        }

        $idem = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idem === '') {
            throw new InvalidArgumentException('idempotency_key is required.');
        }

        $existing = WiseCommerceEvent::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('idempotency_key', $idem)
            ->first();

        if ($existing) {
            return ['event' => $existing, 'created' => false];
        }

        $conversationId = isset($payload['conversation_id'])
            ? trim((string) $payload['conversation_id'])
            : null;
        if ($conversationId === '') {
            $conversationId = null;
        }

        $turnId = isset($payload['turn_id']) ? (int) $payload['turn_id'] : null;
        if ($turnId !== null && $turnId > 0) {
            $owns = WiseTurn::query()
                ->where('id', $turnId)
                ->where('wise_api_key_id', $apiKey->id)
                ->exists();
            if (! $owns) {
                throw new InvalidArgumentException('turn_id not found for this API key.');
            }
        } else {
            $turnId = null;
        }

        // If conversation known but turn omitted, soft-link latest prior turn in that thread.
        if ($turnId === null && $conversationId !== null) {
            $turnId = WiseTurn::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('conversation_id', $conversationId)
                ->latest('id')
                ->value('id');
            $turnId = $turnId !== null ? (int) $turnId : null;
        }

        $occurred = isset($payload['occurred_at']) && $payload['occurred_at'] !== ''
            ? Carbon::parse((string) $payload['occurred_at'])
            : now();

        $amount = $payload['amount'] ?? null;
        if ($amount !== null && $amount !== '') {
            $amount = round((float) $amount, 2);
            if ($amount < 0) {
                throw new InvalidArgumentException('amount must be >= 0.');
            }
        } else {
            $amount = null;
        }

        $meta = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $meta['schema_version'] = CommerceEventTypes::VERSION;
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $meta = array_merge($meta, $payload['meta']);
        }

        $event = WiseCommerceEvent::create([
            'wise_api_key_id' => $apiKey->id,
            'event_type' => $type,
            'conversation_id' => $conversationId,
            'wise_turn_id' => $turnId,
            'external_order_id' => isset($payload['external_order_id'])
                ? mb_substr(trim((string) $payload['external_order_id']), 0, 191)
                : null,
            'platform' => isset($payload['platform'])
                ? mb_substr(trim((string) $payload['platform']), 0, 40)
                : ($meta['platform'] ?? null),
            'amount' => $amount,
            'currency' => isset($payload['currency'])
                ? mb_substr(strtoupper(trim((string) $payload['currency'])), 0, 8)
                : null,
            'occurred_at' => $occurred,
            'idempotency_key' => $idem,
            'meta' => $meta ?: null,
        ]);

        return ['event' => $event, 'created' => true];
    }
}
