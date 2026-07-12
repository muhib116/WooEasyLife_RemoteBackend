<?php

namespace App\Services;

class SubscriptionPaymentConfigService
{
    public function __construct(
        private LandingSettingsService $landingSettings,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function methods(): array
    {
        $accounts = [
            'bKash' => $this->landingSettings->bkashNumber(),
            'Rocket' => $this->landingSettings->rocketNumber(),
            'Nagad' => $this->landingSettings->nagadNumber(),
        ];

        return collect(config('subscription_payments.methods', []))
            ->map(function (array $method) use ($accounts) {
                $partner = (string) ($method['payment_partner'] ?? '');

                if ($partner !== '' && filled($accounts[$partner] ?? null)) {
                    $method['account'] = $accounts[$partner];
                }

                return $method;
            })
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
