<?php

use App\Models\FraudPartnerCredential;
use App\Models\User;
use App\Services\FraudCheck\FraudPartnerCredentialResolver;
use Illuminate\Support\Facades\Hash;

function createFraudAdmin(): User
{
    return User::create([
        'name' => 'Fraud Admin',
        'email' => 'fraud-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('stores multiple encrypted credentials per courier and prefers lowest priority', function () {
    FraudPartnerCredential::query()->create([
        'courier' => 'redx',
        'label' => 'Backup',
        'identifier' => '01911111111',
        'secret' => 'backup-pass',
        'is_active' => true,
        'priority' => 200,
    ]);

    FraudPartnerCredential::query()->create([
        'courier' => 'redx',
        'label' => 'Primary',
        'identifier' => '01922222222',
        'secret' => 'primary-pass',
        'is_active' => true,
        'priority' => 10,
    ]);

    $resolver = app(FraudPartnerCredentialResolver::class);
    $primary = $resolver->primary('redx');

    expect($primary)->not->toBeNull()
        ->and($primary['identifier'])->toBe('01922222222')
        ->and($primary['password'])->toBe('primary-pass')
        ->and($primary['source'])->toBe('database');

    $candidates = $resolver->candidates('redx');
    expect($candidates[0]['identifier'])->toBe('01922222222')
        ->and($candidates[1]['identifier'])->toBe('01911111111');
});

it('shuffles login candidates randomly when session needs a fresh login', function () {
    FraudPartnerCredential::query()->create([
        'courier' => 'paperfly',
        'label' => 'A',
        'identifier' => 'user-a',
        'secret' => 'pass-a',
        'is_active' => true,
        'priority' => 1,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'paperfly',
        'label' => 'B',
        'identifier' => 'user-b',
        'secret' => 'pass-b',
        'is_active' => true,
        'priority' => 2,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'paperfly',
        'label' => 'C',
        'identifier' => 'user-c',
        'secret' => 'pass-c',
        'is_active' => true,
        'priority' => 3,
    ]);

    $resolver = app(FraudPartnerCredentialResolver::class);
    $seenFirst = [];

    for ($i = 0; $i < 40; $i++) {
        $login = $resolver->loginCandidates('paperfly');
        $dbIds = collect($login)->where('source', 'database')->pluck('identifier')->sort()->values()->all();
        $seenFirst[$login[0]['identifier']] = true;
        expect($dbIds)->toBe(['user-a', 'user-b', 'user-c']);
    }

    expect(count($seenFirst))->toBeGreaterThan(1);
});

it('requires platform admin auth for credential routes', function () {
    $this->postJson(route('frauds.credentials.store'), [
        'courier' => 'carrybee',
        'identifier' => '01712345678',
        'secret' => 'secret-pass',
    ])->assertUnauthorized();

    $admin = createFraudAdmin();

    $this->actingAs($admin)->postJson(route('frauds.credentials.store'), [
        'courier' => 'carrybee',
        'label' => 'Main',
        'identifier' => '01712345678',
        'secret' => 'secret-pass',
        'priority' => 50,
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('credential.courier', 'carrybee')
        ->assertJsonPath('credential.identifier', '01712345678')
        ->assertJsonMissing(['secret' => 'secret-pass']);

    $id = FraudPartnerCredential::query()->firstOrFail()->id;

    $this->actingAs($admin)->putJson(route('frauds.credentials.update', $id), [
        'courier' => 'carrybee',
        'label' => 'Main updated',
        'identifier' => '01712345678',
        'priority' => 5,
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('credential.label', 'Main updated')
        ->assertJsonPath('credential.is_active', false);

    $this->actingAs($admin)->get(route('frauds.credentials'))
        ->assertOk();

    $this->actingAs($admin)->deleteJson(route('frauds.credentials.destroy', $id))
        ->assertOk();

    expect(FraudPartnerCredential::query()->count())->toBe(0);
});

it('normalizes redx phone identifiers on save', function () {
    $admin = createFraudAdmin();

    $this->actingAs($admin)->postJson(route('frauds.credentials.store'), [
        'courier' => 'redx',
        'identifier' => '+8801712345678',
        'secret' => 'secret-pass',
    ])->assertCreated()
        ->assertJsonPath('credential.identifier', '01712345678');
});
