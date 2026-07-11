<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config([
        'app.deploy_secret' => 'test-deploy-secret',
        'app.deploy_allow_setup' => false,
    ]);
});

it('returns 404 when deploy secret is wrong', function () {
    $this->postJson('/deploy', [], [
        'X-Deploy-Secret' => 'wrong-secret',
    ])->assertNotFound();

    $this->postJson('/deploy/setup', [], [
        'X-Deploy-Secret' => 'wrong-secret',
    ])->assertNotFound();
});

it('returns 404 when deploy secret is not configured', function () {
    config(['app.deploy_secret' => null]);

    $this->postJson('/deploy', [], [
        'X-Deploy-Secret' => 'test-deploy-secret',
    ])->assertNotFound();
});

it('rejects legacy get deploy urls with secret in path', function () {
    $this->get('/deploy/test-deploy-secret')->assertNotFound();
    $this->get('/deploy/test-deploy-secret/setup')->assertNotFound();
});

it('rejects setup when DEPLOY_ALLOW_SETUP is false', function () {
    $this->postJson('/deploy/setup', [], [
        'X-Deploy-Secret' => 'test-deploy-secret',
    ])->assertNotFound();
});

it('runs production deploy commands with header secret', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('optimize:clear', [])
        ->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andReturn(0);

    Artisan::shouldReceive('call')
        ->once()
        ->with('storage:link', [])
        ->andReturn(0);

    Artisan::shouldReceive('call')
        ->once()
        ->with('optimize', [])
        ->andReturn(0);

    Artisan::shouldReceive('call')
        ->once()
        ->with('order-intelligence:reindex-search', [])
        ->andReturn(0);

    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:restart', [])
        ->andReturn(0);

    $this->postJson('/deploy', [], [
        'X-Deploy-Secret' => 'test-deploy-secret',
    ])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
        ])
        ->assertJsonStructure([
            'status',
            'message',
            'results' => [
                'optimize:clear',
                'migrate',
                'storage:link',
                'optimize',
                'order-intelligence:reindex-search',
                'queue:restart',
            ],
        ]);
});

it('accepts bearer token for deploy', function () {
    Artisan::shouldReceive('call')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->postJson('/deploy', [], [
        'Authorization' => 'Bearer test-deploy-secret',
    ])->assertOk();
});

it('runs setup when explicitly allowed', function () {
    config(['app.deploy_allow_setup' => true]);

    Artisan::shouldReceive('call')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->postJson('/deploy/setup', [], [
        'X-Deploy-Secret' => 'test-deploy-secret',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

it('removes leftover public debug routes', function () {
    $this->get('/get-ip')->assertNotFound();
    $this->get('/send-message')->assertNotFound();
});
