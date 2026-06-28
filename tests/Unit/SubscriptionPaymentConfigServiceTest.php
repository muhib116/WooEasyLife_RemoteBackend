<?php

namespace Tests\Unit;

use App\Services\SubscriptionPaymentConfigService;
use Tests\TestCase;

class SubscriptionPaymentConfigServiceTest extends TestCase
{
    public function test_for_api_returns_configured_payment_methods(): void
    {
        config([
            'subscription_payments.methods' => [
                [
                    'payment_partner' => 'bKash',
                    'account' => '01711111111',
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
