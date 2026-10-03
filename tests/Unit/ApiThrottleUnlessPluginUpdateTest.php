<?php

namespace Tests\Unit;

use App\Http\Middleware\ApiThrottleUnlessPluginUpdate;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiThrottleUnlessPluginUpdateTest extends TestCase
{
    public function test_skips_throttle_for_plugin_update_paths(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects($this->never())->method('limiter');

        $middleware = new ApiThrottleUnlessPluginUpdate($limiter);
        $request = Request::create('/download-plugins', 'GET');
        $called = false;
        $response = $middleware->handle($request, function () use (&$called) {
            $called = true;
            return new Response('ok', 200);
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_skips_throttle_for_metadata_path(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects($this->never())->method('limiter');

        $middleware = new ApiThrottleUnlessPluginUpdate($limiter);
        $request = Request::create('/get-metadata', 'GET');
        $called = false;
        $middleware->handle($request, function () use (&$called) {
            $called = true;
            return new Response('ok', 200);
        });
        $this->assertTrue($called);
    }

    public function test_applies_named_api_limiter_for_other_paths(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->expects($this->once())
            ->method('limiter')
            ->with('api')
            ->willReturn(function () {
                return \Illuminate\Cache\RateLimiting\Limit::perMinute(100)->by('test');
            });
        $limiter->method('tooManyAttempts')->willReturn(false);
        $limiter->method('hit')->willReturn(1);
        $limiter->method('retriesLeft')->willReturn(99);
        $limiter->method('availableIn')->willReturn(0);

        $middleware = new ApiThrottleUnlessPluginUpdate($limiter);
        $request = Request::create('/app-logo', 'GET');
        $response = $middleware->handle($request, fn () => new Response('logo', 200));
        $this->assertSame(200, $response->getStatusCode());
    }
}
