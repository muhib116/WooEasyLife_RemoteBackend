<?php

use App\Models\AccessToken;
use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function inventoryAdmin(): User
{
    return User::create([
        'name' => 'Inventory Admin',
        'email' => 'inventory-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

function inventoryToken(User $user): AccessToken
{
    return AccessToken::unguarded(fn () => AccessToken::create([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'inventory-token',
        'token' => hash('sha256', 'inventory-'.uniqid()),
        'abilities' => ['*'],
        'status' => true,
        'domain' => 'shop.example.com',
    ]));
}

it('shows platform totals and one phone without store fields', function () {
    $admin = inventoryAdmin();
    $merchant = User::create([
        'name' => 'Merchant',
        'email' => 'inventory-merchant-'.uniqid().'@example.com',
        'phone' => '018'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);
    $token = inventoryToken($merchant);

    $customer = PlatformCustomer::query()->create([
        'phone_normalized' => '01711111111',
        'latest_name' => 'Karim',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    CourierCustomerSnapshot::query()->create([
        'platform_customer_id' => $customer->id,
        'phone_normalized' => '01711111111',
        'courier' => 'steadfast',
        'total_order' => 4,
        'confirmed' => 3,
        'cancel' => 1,
        'raw_report' => ['partial' => 2],
        'fetched_at' => now(),
    ]);

    foreach ([
        ['delivered', 'fraud_check', 4521],
        ['canceled', 'webhook', 4522],
        ['partially_delivered', 'courier_create', 4523],
    ] as [$status, $source, $wcId]) {
        PlatformOrder::query()->create([
            'platform_customer_id' => $customer->id,
            'access_token_id' => $token->id,
            'user_id' => $merchant->id,
            'wc_order_id' => $wcId,
            'current_status' => $status,
            'status_changed_at' => now(),
            'source' => $source,
            'order_amount' => 500,
            'currency' => 'BDT',
        ]);
    }

    $this->actingAs($admin)
        ->get(route('frauds.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('FraudCheck/Dashboard')
            ->where('summary.phones', 1)
            ->where('summary.snapshots', 1)
            ->where('summary.plugin_orders', 3)
            ->where('summary.courier_totals.delivered', 3)
            ->where('summary.courier_totals.cancelled', 1)
            ->where('summary.courier_totals.partial', 2)
            ->where('summary.order_status.0.key', 'new_order')
            ->missing('summary.stores')
            ->where('person', null));

    $this->actingAs($admin)
        ->get(route('frauds.dashboard', ['phone' => '01711111111']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('person.found', true)
            ->where('person.phone', '01711111111')
            ->where('person.name', 'Karim')
            ->where('person.orders.total', 3)
            ->where('person.snapshots.0.partial', 2)
            ->missing('person.orders.recent.0.user_id')
            ->missing('person.orders.recent.0.access_token_id')
            ->where('phoneError', null));

    $this->actingAs($admin)
        ->get(route('frauds.dashboard', ['phone' => 'not-a-phone']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('person', null)
            ->where('phoneError', 'The provided phone number is invalid. Use a Bangladesh mobile number like 017XXXXXXXX.'));
});
