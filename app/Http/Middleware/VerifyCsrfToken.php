<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/put-message',
        'api/messenger/oauth/callback',
        'api/messenger/oauth/select-page',
        'api/webhooks/messenger',
        'fraud-stream',
        'deploy',
        'deploy/setup',
        'blog/analytics/event',
        'analytics/visitors/event',
    ];
}
