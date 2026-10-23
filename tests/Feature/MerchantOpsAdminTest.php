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

    public function test_merchant_list_counts_licenses_by_state(): void
    {
        $admin = $this->createAdmin();
        $merchant = User::create([
            'name' => 'License Shop',
            'email' => 'licenses-' . uniqid() . '@example.com',
            'phone' => '01755556666',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $make = function (array $extra) use ($merchant) {
            AccessToken::unguarded(function () use ($merchant, $extra) {
                AccessToken::create(array_merge([
                    'tokenable_type' => User::class,
                    'tokenable_id' => $merchant->id,
                    'name' => 'License',
                    'token' => hash('sha256', uniqid('license', true)),
                    'domain' => 'shop.example.com',
                    'status' => true,
                ], $extra));
            });
        };

        $make(['status' => true, 'expires_at' => now()->addMonth()]);
        $make(['status' => true]);
        $make(['status' => false, 'expires_at' => now()->addMonth()]);
        $make(['status' => true, 'expires_at' => now()->subDay()]);
        $make(['status' => false, 'expires_at' => now()->subDay()]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.licenses.total', 5)
                ->where('stats.licenses.active', 2)
                ->where('stats.licenses.inactive', 1)
                ->where('stats.licenses.expired', 2)
            );
    }

    public function test_license_list_filters_by_state_and_hides_the_key(): void
    {
        $admin = $this->createAdmin();
        $merchant = User::create([
            'name' => 'Follow Up Shop',
            'email' => 'followup-' . uniqid() . '@example.com',
            'phone' => '01755556666',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $secret = hash('sha256', 'license-secret-' . uniqid());

        $package = UserPackage::create([
            'title' => 'Growth',
            'domain' => 'followup.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 40,
            'total_order_handled' => 60,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        AccessToken::unguarded(function () use ($merchant, $secret, $package) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'License',
                'title' => 'Shop key',
                'token' => $secret,
                'domain' => 'followup.example.com',
                'status' => false,
                'expires_at' => now()->subDay(),
                'user_package_id' => $package->id,
            ]);
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'License',
                'title' => 'Live key',
                'token' => hash('sha256', 'live-' . uniqid()),
                'domain' => 'live.example.com',
                'status' => true,
                'expires_at' => now()->addMonth(),
            ]);
        });

        $this->actingAs($admin)
            ->get(route('users.licenses', ['state' => 'expired']))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Licenses')
                ->where('state', 'expired')
                ->where('counts.expired', 1)
                ->where('counts.active', 1)
                ->has('licenses', 1)
                ->where('licenses.0.domain', 'followup.example.com')
                ->where('licenses.0.state', 'expired')
                ->where('licenses.0.merchant.phone', '01755556666')
                ->where('licenses.0.plan.title', 'Growth')
                ->missing('licenses.0.token')
            );

        $this->actingAs($admin)
            ->get(route('users.licenses', ['state' => 'not-a-state']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'all')
                ->has('licenses', 2)
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
