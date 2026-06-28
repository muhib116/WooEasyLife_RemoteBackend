<?php

namespace App\Services;

class SubscriptionPaymentConfigService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function methods(): array
    {
        return collect(config('subscription_payments.methods', []))
            ->filter(fn (array $method) => ! empty($method['payment_partner']) && ! empty($method['account']))
            ->values()
            ->all();
    }

    /**
     * Plugin/API-safe payment instructions (no secrets beyond public account numbers).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forApi(): array
    {
        return collect($this->methods())
            ->map(fn (array $method) => [
                'payment_partner' => $method['payment_partner'],
                'account' => $method['account'],
                'note' => $method['note'] ?? null,
                'steps' => $method['steps'] ?? [],
            ])
            ->values()
            ->all();
    }
}
