<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MerchantOpsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    private function createMerchantWithExpiredPlan(): User
    {
        $merchant = User::create([
            'name' => 'Expired Shop',
            'email' => 'expired-' . uniqid() . '@example.com',
            'phone' => '01711112222',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'expired.example.com',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Starter',
            'domain' => 'expired.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 40,
            'total_order_handled' => 60,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDays(2),
        ]);

        AccessToken::unguarded(function () use ($merchant) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'License',
                'token' => hash('sha256', 'expired-shop-token'),
                'domain' => 'expired.example.com',
                'status' => true,
            ]);
        });

        return $merchant;
    }

    public function test_merchant_list_includes_domain_and_expired_attention(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchantWithExpiredPlan();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users')
                ->has('filters.search')
                ->has('stats.total')
                ->where('users', function ($users) use ($merchant) {
                    $row = collect($users)->firstWhere('id', $merchant->id);

                    return $row
                        && in_array('expired.example.com', $row['domains'] ?? [], true)
                        && ($row['attention']['code'] ?? null) === 'expired'
                        && ($row['name'] ?? null) === 'Expired Shop';
                })
            );
    }

    public function test_merchant_list_search_filters_on_the_server(): void
    {
        $admin = $this->createAdmin();
        $match = $this->createMerchantWithExpiredPlan();
        $other = User::create([
            'name' => 'Other Shop',
            'email' => 'other-' . uniqid() . '@example.com',
            'phone' => '01733334444',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'expired.example.com']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('filters.search', 'expired.example.com')
                ->where('users', function ($users) use ($match, $other) {
                    $ids = collect($users)->pluck('id')->all();

                    return in_array($match->id, $ids, true)
                        && ! in_array($other->id, $ids, true);
                })
                ->where('stats.total', fn ($total) => $total >= 3)
            );
    }

    public function test_merchant_list_search_matches_phone_and_name(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchantWithExpiredPlan();

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => '01711112222']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', function ($users) use ($merchant) {
                    $ids = collect($users)->pluck('id')->all();

                    return in_array($merchant->id, $ids, true);
                })
            );

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'Expired']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', function ($users) use ($merchant) {
                    return collect($users)->contains('id', $merchant->id);
                })
            );
    }

    public function test_subscription_alerts_page_lists_expired_plan(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchantWithExpiredPlan();

        $this->actingAs($admin)
            ->get(route('subscriptionAlerts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SubscriptionAlerts/Index')
                ->where('summary.danger', fn ($count) => $count >= 1)
                ->where('alerts', function ($alerts) use ($merchant) {
                    return collect($alerts)->contains(function ($alert) use ($merchant) {
                        return ($alert['type'] ?? null) === 'subscription_expired'
                            && (int) ($alert['user_id'] ?? 0) === (int) $merchant->id
                            && ($alert['domain'] ?? null) === 'expired.example.com';
                    });
                })
            );
    }

    public function test_merchant_overview_includes_website_health(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchantWithExpiredPlan();

        $this->actingAs($admin)
            ->get(route('users.view', $merchant->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/View')
                ->has('report.active_api_key')
                ->where('websiteHealth.0.domain', 'expired.example.com')
            );
    }
}
