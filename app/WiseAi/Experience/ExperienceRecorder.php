<?php

namespace App\WiseAi\Experience;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseCommerceEvent;
use App\Models\WiseAi\WiseExperienceSignal;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseTurn;
use InvalidArgumentException;

/**
 * Records what worked — never writes Knowledge.
 */
class ExperienceRecorder
{
    public const VERSION = 'experience-0.1.0';

    /** @var list<string> */
    public const SIGNAL_TYPES = [
        'accepted',
        'edited',
        'rejected',
        'commerce_assist',
        'external',
    ];

    public function fromFeedback(WiseFeedback $feedback, WiseTurn $turn): ?WiseExperienceSignal
    {
        $outcome = (string) $feedback->outcome;
        $type = match ($outcome) {
            'approved' => 'accepted',
            'edited' => 'edited',
            'rejected' => 'rejected',
            default => null,
        };
        if ($type === null) {
            return null;
        }

        $weight = match ($type) {
            'accepted' => 1.0,
            'edited' => 0.5,
            'rejected' => -1.0,
            default => 0.0,
        };

        $decision = is_array($turn->decision) ? $turn->decision : [];
        $scriptId = is_array($decision['dialogue']['script'] ?? null)
            ? (string) ($decision['dialogue']['script']['id'] ?? '')
            : '';

        $apiKey = WiseApiKey::query()->find($feedback->wise_api_key_id);
        if (! $apiKey) {
            return null;
        }

        return $this->store($apiKey, [
            'signal_type' => $type,
            'intent' => (string) ($decision['intent'] ?? ''),
            'action' => (string) ($decision['action'] ?? ''),
            'source' => (string) ($decision['source'] ?? ''),
            'pattern_key' => $scriptId !== '' ? 'script:'.$scriptId : null,
            'weight' => $weight,
            'idempotency_key' => 'feedback:'.$feedback->id,
            'wise_turn_id' => $turn->id,
            'meta' => [
                'via' => $feedback->meta['via'] ?? 'feedback',
                'outcome' => $outcome,
                'reason_code' => $feedback->reason_code,
                'experience_version' => self::VERSION,
            ],
        ]);
    }

    public function fromCommercePaid(WiseCommerceEvent $event, ?WiseTurn $turn = null): ?WiseExperienceSignal
    {
        if ($event->event_type !== 'order_paid') {
            return null;
        }

        $apiKey = WiseApiKey::query()->find($event->wise_api_key_id);
        if (! $apiKey) {
            return null;
        }

        $decision = is_array($turn?->decision) ? $turn->decision : [];

        return $this->store($apiKey, [
            'signal_type' => 'commerce_assist',
            'intent' => (string) ($decision['intent'] ?? 'order'),
            'action' => (string) ($decision['action'] ?? ''),
            'source' => (string) ($decision['source'] ?? ''),
            'pattern_key' => null,
            'weight' => 1.5,
            'idempotency_key' => 'commerce:'.$event->id,
            'wise_turn_id' => $event->wise_turn_id,
            'meta' => [
                'via' => 'commerce',
                'event_type' => $event->event_type,
                'experience_version' => self::VERSION,
            ],
        ]);
    }

    /**
     * External trainer / adapter intake.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromExternal(WiseApiKey $apiKey, array $payload): WiseExperienceSignal
    {
        $type = (string) ($payload['signal_type'] ?? 'external');
        if (! in_array($type, self::SIGNAL_TYPES, true)) {
            throw new InvalidArgumentException('Invalid signal_type.');
        }

        $weight = isset($payload['weight']) ? (float) $payload['weight'] : 1.0;
        $weight = max(-5.0, min(5.0, $weight));

        $idem = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idem === '') {
            $idem = 'ext:'.sha1(json_encode([
                $apiKey->id,
                $type,
                $payload['intent'] ?? '',
                $payload['pattern_key'] ?? '',
                $payload['action'] ?? '',
                microtime(true),
            ]));
        }

        return $this->store($apiKey, [
            'signal_type' => $type === 'external' ? 'external' : $type,
            'intent' => isset($payload['intent']) ? mb_substr((string) $payload['intent'], 0, 60) : null,
            'action' => isset($payload['action']) ? mb_substr((string) $payload['action'], 0, 40) : null,
            'source' => isset($payload['source']) ? mb_substr((string) $payload['source'], 0, 40) : null,
            'pattern_key' => isset($payload['pattern_key']) ? mb_substr((string) $payload['pattern_key'], 0, 120) : null,
            'weight' => $weight,
            'idempotency_key' => mb_substr($idem, 0, 191),
            'wise_turn_id' => isset($payload['turn_id']) ? (int) $payload['turn_id'] : null,
            'meta' => [
                'via' => 'external',
                'context' => is_array($payload['context'] ?? null) ? $payload['context'] : null,
                'experience_version' => self::VERSION,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function store(WiseApiKey $apiKey, array $row): WiseExperienceSignal
    {
        $idem = $row['idempotency_key'] ?? null;
        if (is_string($idem) && $idem !== '') {
            $existing = WiseExperienceSignal::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('idempotency_key', $idem)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return WiseExperienceSignal::create([
            'wise_api_key_id' => $apiKey->id,
            'signal_type' => $row['signal_type'],
            'intent' => ($row['intent'] ?? '') !== '' ? $row['intent'] : null,
            'action' => ($row['action'] ?? '') !== '' ? $row['action'] : null,
            'source' => ($row['source'] ?? '') !== '' ? $row['source'] : null,
            'pattern_key' => ($row['pattern_key'] ?? '') !== '' ? $row['pattern_key'] : null,
            'weight' => (float) ($row['weight'] ?? 0),
            'idempotency_key' => $idem,
            'wise_turn_id' => $row['wise_turn_id'] ?? null,
            'meta' => $row['meta'] ?? null,
        ]);
    }
}
