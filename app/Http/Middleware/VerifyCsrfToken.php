<?php

namespace App\Http\Middleware;

use App\Support\PanelSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

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

    /**
     * Merchant pages keep a separate XSRF cookie so admin forms in another tab
     * are not invalidated.
     */
    protected function newCookie($request, $config)
    {
        return new Cookie(
            PanelSession::xsrfCookieNameFor($request),
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
