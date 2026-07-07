<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['app.deploy_secret' => 'test-deploy-secret']);
});

it('returns 404 when deploy secret is wrong', function () {
    $this->get('/deploy/wrong-secret')->assertNotFound();
    $this->get('/deploy/wrong-secret/setup')->assertNotFound();
});

it('returns 404 when deploy secret is not configured', function () {
    config(['app.deploy_secret' => null]);

    $this->get('/deploy/test-deploy-secret')->assertNotFound();
});

it('runs production deploy commands', function () {
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

    $this->get('/deploy/test-deploy-secret')
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
