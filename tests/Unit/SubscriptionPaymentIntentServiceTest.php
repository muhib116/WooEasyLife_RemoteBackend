<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\UserPackage;
use App\Services\SubscriptionPaymentIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPaymentIntentServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPaymentIntentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionPaymentIntentService::class);
    }

    public function test_resolve_intent_for_new_subscription(): void
    {
        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $this->assertSame(
            SubscriptionPaymentIntentService::INTENT_SUBSCRIBE,
            $this->service->resolveIntent(null, $plan)
        );
    }

    public function test_resolve_intent_for_renew_and_upgrade(): void
    {
        $current = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $premium = PackageHub::create([
            'title' => 'Premium',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $basic = PackageHub::create([
            'title' => 'Basic',
            'per_order_rate' => 0.5,
            'is_active' => true,
        ]);

        $activePackage = new UserPackage([
            'package_hub_id' => $current->id,
            'plan_type' => 'legacy',
        ]);

        $this->assertSame(
            SubscriptionPaymentIntentService::INTENT_RENEW,
            $this->service->resolveIntent($activePackage, $current)
        );

        $this->assertSame(
            SubscriptionPaymentIntentService::INTENT_UPGRADE,
            $this->service->resolveIntent($activePackage, $premium)
        );

        $this->assertSame(
            SubscriptionPaymentIntentService::INTENT_DOWNGRADE,
            $this->service->resolveIntent($activePackage, $basic)
        );
    }

    public function test_resolve_intent_for_catalog_downgrade(): void
    {
        $growth = PackageHub::create([
            'title' => 'Growth',
            'per_order_rate' => 0,
            'package_price' => 1500,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'is_active' => true,
            'index' => 2,
        ]);

        $starter = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_price' => 500,
            'package_duration' => '1_month',
            'order_rate_token' => 300,
            'is_active' => true,
            'index' => 1,
        ]);

        $activePackage = new UserPackage([
            'package_hub_id' => $growth->id,
            'plan_type' => 'catalog',
        ]);

        $this->assertSame(
            SubscriptionPaymentIntentService::INTENT_DOWNGRADE,
            $this->service->resolveIntent($activePackage, $starter)
        );
    }

    public function test_billing_capabilities_for_active_plan(): void
    {
        $activePackage = new UserPackage(['package_hub_id' => 1]);

        $capabilities = $this->service->billingCapabilities($activePackage, 'active');

        $this->assertFalse($capabilities['can_renew_current_plan']);
        $this->assertTrue($capabilities['can_upgrade_plan']);
        $this->assertTrue($capabilities['can_submit_payment']);
        $this->assertFalse($capabilities['can_subscribe_plan']);
    }

    public function test_billing_capabilities_block_actions_while_payment_pending(): void
    {
        $activePackage = new UserPackage(['package_hub_id' => 1]);

        $capabilities = $this->service->billingCapabilities($activePackage, 'active', true);

        $this->assertFalse($capabilities['can_renew_current_plan']);
        $this->assertFalse($capabilities['can_upgrade_plan']);
        $this->assertFalse($capabilities['can_submit_payment']);
        $this->assertFalse($capabilities['can_subscribe_plan']);
    }

    public function test_billing_capabilities_for_exhausted_plan(): void
    {
        $activePackage = new UserPackage(['package_hub_id' => 1]);

        $capabilities = $this->service->billingCapabilities($activePackage, 'exhausted');

        $this->assertTrue($capabilities['can_renew_current_plan']);
        $this->assertTrue($capabilities['can_upgrade_plan']);
        $this->assertTrue($capabilities['can_submit_payment']);
        $this->assertFalse($capabilities['can_subscribe_plan']);
    }

    public function test_validate_renew_while_active_is_rejected(): void
    {
        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $activePackage = new UserPackage([
            'package_hub_id' => $plan->id,
            'plan_type' => 'legacy',
        ]);

        $result = $this->service->validateSubmission($activePackage, $plan, 'active');

        $this->assertFalse($result['allowed']);
        $this->assertSame(SubscriptionPaymentIntentService::INTENT_RENEW, $result['intent']);
    }

    public function test_validate_downgrade_while_active_is_allowed(): void
    {
        $growth = PackageHub::create([
            'title' => 'Growth',
            'per_order_rate' => 0,
            'package_price' => 1500,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'is_active' => true,
            'index' => 2,
        ]);

        $starter = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_price' => 500,
            'package_duration' => '1_month',
            'order_rate_token' => 300,
            'is_active' => true,
            'index' => 1,
        ]);

        $activePackage = new UserPackage([
            'package_hub_id' => $growth->id,
            'plan_type' => 'catalog',
        ]);

        $result = $this->service->validateSubmission($activePackage, $starter, 'active');

        $this->assertTrue($result['allowed']);
        $this->assertSame(SubscriptionPaymentIntentService::INTENT_DOWNGRADE, $result['intent']);
    }

    public function test_validate_upgrade_while_active_is_allowed(): void
    {
        $current = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $premium = PackageHub::create([
            'title' => 'Premium',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $activePackage = new UserPackage([
            'package_hub_id' => $current->id,
            'plan_type' => 'legacy',
        ]);

        $result = $this->service->validateSubmission($activePackage, $premium, 'active');

        $this->assertTrue($result['allowed']);
        $this->assertSame(SubscriptionPaymentIntentService::INTENT_UPGRADE, $result['intent']);
    }

    public function test_enforcement_is_disabled_without_config_flag(): void
    {
        config(['subscription_payments.enforce_intent_rules' => false]);

        $this->assertFalse($this->service->shouldEnforceIntentRules(null));
        $this->assertFalse($this->service->shouldEnforceIntentRules(new \App\Models\AccessToken()));
    }

    public function test_enforcement_applies_to_plugin_api_only_by_default(): void
    {
        config([
            'subscription_payments.enforce_intent_rules' => true,
            'subscription_payments.enforce_intent_plugin_api_only' => true,
        ]);

        $this->assertFalse($this->service->shouldEnforceIntentRules(null));
        $this->assertTrue($this->service->shouldEnforceIntentRules(new \App\Models\AccessToken()));
    }

    public function test_enforcement_can_apply_to_all_channels_when_configured(): void
    {
        config([
            'subscription_payments.enforce_intent_rules' => true,
            'subscription_payments.enforce_intent_plugin_api_only' => false,
        ]);

        $this->assertTrue($this->service->shouldEnforceIntentRules(null));
    }
}
