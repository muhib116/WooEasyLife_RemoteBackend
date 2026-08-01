<?php

namespace App\WiseAi\Contracts;

/**
 * Normalized inbound turn — every channel adapter should produce this shape.
 */
final class IncomingTurn
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $text,
        public readonly string $channel = 'api',
        public readonly ?string $conversationId = null,
        public readonly array $context = [],
    ) {}

    /**
     * @param  array{text: string, channel?: ?string, conversation_id?: ?string, context?: ?array}  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            text: (string) $payload['text'],
            channel: (string) ($payload['channel'] ?? 'api'),
            conversationId: isset($payload['conversation_id']) ? (string) $payload['conversation_id'] : null,
            context: is_array($payload['context'] ?? null) ? $payload['context'] : [],
        );
    }
}
