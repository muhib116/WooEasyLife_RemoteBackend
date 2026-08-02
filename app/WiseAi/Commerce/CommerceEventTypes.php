<?php

namespace App\WiseAi\Commerce;

/**
 * Platform-agnostic commerce event taxonomy (Wave C4).
 * Adapters map store webhooks → these types; Wise never imports store models.
 */
class CommerceEventTypes
{
    public const VERSION = '1.0';

    public const CHECKOUT_STARTED = 'checkout_started';

    public const ORDER_CREATED = 'order_created';

    public const ORDER_PAID = 'order_paid';

    public const ORDER_CANCELED = 'order_canceled';

    public const ORDER_DELIVERED = 'order_delivered';

    public const ORDER_RETURNED = 'order_returned';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CHECKOUT_STARTED,
            self::ORDER_CREATED,
            self::ORDER_PAID,
            self::ORDER_CANCELED,
            self::ORDER_DELIVERED,
            self::ORDER_RETURNED,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Events that can count toward attributed GMV (when amount present).
     *
     * @return list<string>
     */
    public static function gmvTypes(): array
    {
        return [self::ORDER_CREATED, self::ORDER_PAID];
    }

    /**
     * @return list<string>
     */
    public static function lostSaleTypes(): array
    {
        return [self::ORDER_CANCELED, self::ORDER_RETURNED];
    }
}
