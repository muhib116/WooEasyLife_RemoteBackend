<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function createPlatformAdmin(): User
{
    return User::create([
        'name' => 'Admin',
        'email' => 'admin-intel-' . uniqid() . '@example.com',
        'phone' => '018' . random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('loads order intelligence dashboard for platform admin', function () {
    $admin = createPlatformAdmin();

    $this->actingAs($admin)
        ->get(route('orderIntelligence.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OrderIntelligence/Index')
            ->has('merchants')
            ->has('dashboard.summary')
        );
});

it('loads order intelligence sub pages for platform admin', function () {
    $admin = createPlatformAdmin();

    $this->actingAs($admin)->get(route('orderIntelligence.customers'))->assertOk();
    $this->actingAs($admin)->get(route('orderIntelligence.orders'))->assertOk();
    $this->actingAs($admin)->get(route('orderIntelligence.records'))->assertOk();
    $this->actingAs($admin)->get(route('orderIntelligence.apiDocs'))->assertOk();
});

it('blocks merchants from order intelligence admin', function () {
    [$user] = createOrderIntelligenceToken();

    $this->actingAs($user)
        ->get(route('orderIntelligence.index'))
        ->assertRedirect(route('portal.dashboard'));
});

it('returns customer list json for admin', function () {
    $admin = createPlatformAdmin();

    $this->actingAs($admin)
        ->get(route('orderIntelligence.customersList'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});
