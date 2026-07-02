<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePluginUploadApiKey
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.plugin_upload.api_key');

        if ($expected === '') {
            return $this->errorResponse('Plugin upload API is not configured', 503);
        }

        $provided = (string) ($request->bearerToken() ?? '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return $this->errorResponse('Unauthorized', 401);
        }

        return $next($request);
    }
}
