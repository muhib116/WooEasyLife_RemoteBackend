<?php

use App\Models\WhitelistedDomain;
use App\Services\WhitelistedDomainService;

it('allows only active whitelisted domains', function () {
    app(WhitelistedDomainService::class)->forgetCache();

    WhitelistedDomain::create([
        'domain' => 'allowed.example.com',
        'is_active' => true,
    ]);

    WhitelistedDomain::create([
        'domain' => 'inactive.example.com',
        'is_active' => false,
    ]);

    $service = app(WhitelistedDomainService::class);

    expect($service->isAllowed('allowed.example.com'))->toBeTrue();
    expect($service->isAllowed('inactive.example.com'))->toBeFalse();
    expect($service->isAllowed('blocked.example.com'))->toBeFalse();
});

it('normalizes domain values before checking whitelist', function () {
    $service = app(WhitelistedDomainService::class);

    expect($service->normalizeDomain('https://WWW.Example.com/store'))->toBe('www.example.com');
});

it('clears whitelist cache after domain changes', function () {
    $service = app(WhitelistedDomainService::class);
    $service->forgetCache();

    WhitelistedDomain::create([
        'domain' => 'cache-test.example.com',
        'is_active' => true,
    ]);

    expect($service->activeDomains())->toContain('cache-test.example.com');

    WhitelistedDomain::first()->update(['is_active' => false]);
    $service->forgetCache();

    expect($service->isAllowed('cache-test.example.com'))->toBeFalse();
});
