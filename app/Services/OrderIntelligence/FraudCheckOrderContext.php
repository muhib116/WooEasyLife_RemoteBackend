<?php

namespace App\Services\OrderIntelligence;

class FraudCheckOrderContext
{
    public function __construct(
        public ?int $accessTokenId,
        public ?int $userId,
        public string $phone,
        public ?int $wcOrderId = null,
        public ?string $externalRef = null,
        public ?string $name = null,
        public ?string $address = null,
        public ?string $productTitle = null,
        public ?string $productSku = null,
        public ?int $quantity = null,
        public ?float $orderAmount = null,
        public ?string $currency = 'BDT',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequestPayload(array $payload, ?int $accessTokenId, ?int $userId): self
    {
        $wcOrderId = self::intOrNull($payload['wc_order_id'] ?? $payload['id'] ?? null);
        $externalRef = isset($payload['id']) ? (string) $payload['id'] : null;

        return new self(
            accessTokenId: $accessTokenId,
            userId: $userId,
            phone: (string) ($payload['phone'] ?? ''),
            wcOrderId: $wcOrderId,
            externalRef: $externalRef,
            name: self::stringOrNull($payload['name'] ?? $payload['customer_name'] ?? null),
            address: self::stringOrNull($payload['address'] ?? $payload['customer_address'] ?? null),
            productTitle: self::stringOrNull($payload['product'] ?? $payload['product_title'] ?? null),
            productSku: self::stringOrNull($payload['sku'] ?? $payload['product_sku'] ?? null),
            quantity: self::intOrNull($payload['quantity'] ?? null),
            orderAmount: self::floatOrNull($payload['price'] ?? $payload['order_amount'] ?? null),
            currency: self::stringOrNull($payload['currency'] ?? null) ?? 'BDT',
        );
    }

    public function canIngest(): bool
    {
        return $this->accessTokenId !== null
            && $this->userId !== null
            && $this->wcOrderId !== null
            && $this->wcOrderId > 0;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
