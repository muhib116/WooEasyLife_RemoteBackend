<?php

namespace App\Domain\OrderIntelligence;

enum OrderStatus: string
{
    case NewOrder = 'new_order';
    case CourierEntry = 'courier_entry';
    case CourierHandover = 'courier_handover';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Returned = 'returned';
    case Canceled = 'canceled';

    public static function defaultCounts(): array
    {
        $counts = [];

        foreach (self::cases() as $status) {
            $counts[$status->value] = 0;
        }

        return $counts;
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, config('order_intelligence.terminal_statuses', []), true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        $allowed = config('order_intelligence.transitions.' . $this->value, []);

        return in_array($next->value, $allowed, true);
    }
}
