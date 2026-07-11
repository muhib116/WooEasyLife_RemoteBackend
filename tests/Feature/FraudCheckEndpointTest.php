<?php

use App\Models\User;
use App\Services\FraudCheckService;
use Illuminate\Support\Facades\Hash;

it('requires auth for the admin fraud check web route', function () {
    $this->postJson('/q8w1d9zp7kuo2vrb5m6cnx0ahjls4et3ifyugpdbq2m1vnz0l/fraud-check', [
        'phone' => '01770989591',
    ])->assertUnauthorized();
});

it('returns fraud check report for platform admins', function () {
    $admin = User::create([
        'name' => 'Fraud Endpoint Admin',
        'email' => 'fraud-endpoint-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $this->mock(FraudCheckService::class, function ($mock) {
        $mock->shouldReceive('normalizePhone')->andReturn('01770989591');
        $mock->shouldReceive('getReport')->andReturn([
            'total_order' => 1,
            'confirmed' => 1,
            'cancel' => 0,
            'success_rate' => '100%',
            'frauds' => [],
            'courier' => [
                ['title' => 'Stead Fast', 'report' => ['total_order' => 1, 'confirmed' => 1, 'cancel' => 0]],
            ],
        ]);
    });

    $this->actingAs($admin)
        ->postJson(route('frauds.adminFraudCheck'), [
            'phone' => '01770989591',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'total_order',
            'confirmed',
            'frauds',
            'cancel',
            'success_rate',
            'courier' => [
                ['title', 'report'],
            ],
        ])
        ->assertJsonMissingPath('_debug');
});
