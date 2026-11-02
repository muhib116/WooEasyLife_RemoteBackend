<?php

namespace App\Http\Middleware;

use App\Support\SensitiveQuery;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetReferrerPolicy
{
    /**
     * Keep a customer phone out of third-party referrers and out of the URL
     * Inertia stores for the next history entry.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        $this->stripSensitiveQueryFromInertiaUrl($request, $response);

        return $response;
    }

    private function stripSensitiveQueryFromInertiaUrl(Request $request, Response $response): void
    {
        if (! $request->headers->has('X-Inertia') || ! $response instanceof JsonResponse) {
            return;
        }

        $data = $response->getData(true);

        if (! is_array($data) || ! isset($data['url']) || ! is_string($data['url'])) {
            return;
        }

        $clean = SensitiveQuery::strip($data['url']);

        if ($clean === $data['url']) {
            return;
        }

        $data['url'] = $clean;
        $response->setData($data);
    }
}
