<?php

namespace Tests\Unit;

use App\Services\SubscriptionPaymentConfigService;
use Tests\TestCase;

class SubscriptionPaymentConfigServiceTest extends TestCase
{
    public function test_for_api_returns_landing_settings_account_override(): void
    {
        config([
            'landing.bkash_number' => '01711111111',
            'landing.rocket_number' => null,
            'landing.nagad_number' => null,
            'subscription_payments.methods' => [
                [
                    'payment_partner' => 'bKash',
                    'account' => '01999999999',
                    'note' => 'Test note',
                    'steps' => ['Step one'],
                ],
            ],
        ]);

        $methods = app(SubscriptionPaymentConfigService::class)->forApi();

        $this->assertCount(1, $methods);
        $this->assertSame('bKash', $methods[0]['payment_partner']);
        $this->assertSame('01711111111', $methods[0]['account']);
    }

    public function test_methods_filters_empty_accounts(): void
    {
        config([
            'landing.bkash_number' => null,
            'landing.rocket_number' => null,
            'landing.nagad_number' => null,
            'subscription_payments.methods' => [
                [
                    'payment_partner' => 'bKash',
                    'account' => '',
                    'note' => 'Hidden',
                    'steps' => [],
                ],
            ],
        ]);

        $this->assertSame([], app(SubscriptionPaymentConfigService::class)->methods());
    }
}
