<?php

use Illuminate\Support\Facades\Http;

it('reports a rejected Steadfast portal login without a missing class error', function () {
    config([
        'fraud-checker-bd-courier.steadfast.user' => 'seller@example.com',
        'fraud-checker-bd-courier.steadfast.password' => 'test-password',
    ]);

    Http::preventStrayRequests();
    Http::fake(function ($request) {
        if ($request->method() === 'GET') {
            return Http::response('<input name="_token" value="csrf-token">', 200);
        }

        return Http::response('rejected', 302, [
            'Location' => 'https://www.steadfast.com.bd/login',
        ]);
    });

    $this->artisan('steadfast:refresh-curl', ['phone' => '01770989591'])
        ->assertFailed();
});
