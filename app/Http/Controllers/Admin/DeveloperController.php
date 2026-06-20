<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class DeveloperController extends Controller
{
    public function index()
    {
        return Inertia::render('Developer/Index', [
            'apiBaseUrl' => rtrim(config('app.url'), '/'),
        ]);
    }

    public function proxy(Request $request)
    {
        $validated = $request->validate([
            'method' => 'required|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'required|string|max:500',
            'query' => 'nullable|array',
            'body' => 'nullable',
            'token' => 'nullable|string|max:2000',
            'origin' => 'nullable|string|max:500',
        ]);

        $path = $validated['path'];

        if (!str_starts_with($path, '/') || str_contains($path, '..')) {
            return response()->json([
                'status_code' => 400,
                'body' => ['error' => 'Invalid path.'],
                'is_json' => true,
                'duration_ms' => 0,
            ], 400);
        }

        if (!$this->isAllowedPath($path)) {
            return response()->json([
                'status_code' => 403,
                'body' => ['error' => 'This path is not allowed for proxy requests.'],
                'is_json' => true,
                'duration_ms' => 0,
            ], 403);
        }

        $url = rtrim(config('app.url'), '/') . $path;
        $method = strtoupper($validated['method']);
        $query = $validated['query'] ?? [];
        $body = $validated['body'] ?? null;
        $token = $validated['token'] ?? null;
        $origin = $validated['origin'] ?? null;

        $headers = [
            'Accept' => 'application/json, text/event-stream, */*',
        ];

        if ($origin) {
            $headers['Origin'] = $origin;
            $headers['Referer'] = rtrim($origin, '/') . '/';
        }

        $client = Http::timeout(90)->withHeaders($headers);

        if ($token) {
            $client = $client->withToken($token);
        }

        $start = microtime(true);

        try {
            $response = match ($method) {
                'GET' => $client->get($url, $query),
                'POST' => $this->sendWithOptionalBody($client, 'post', $url, $body),
                'PUT' => $this->sendWithOptionalBody($client, 'put', $url, $body),
                'PATCH' => $this->sendWithOptionalBody($client, 'patch', $url, $body),
                'DELETE' => $client->delete($url, $query),
                default => $client->get($url, $query),
            };
        } catch (\Throwable $e) {
            return response()->json([
                'status_code' => 0,
                'body' => ['error' => $e->getMessage()],
                'is_json' => true,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ], 502);
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $contentType = $response->header('Content-Type') ?? '';
        $rawBody = $response->body();

        $isJson = str_contains($contentType, 'json')
            || str_contains($contentType, 'javascript');

        $parsedBody = $rawBody;

        if ($isJson) {
            try {
                $parsedBody = $response->json();
            } catch (\Throwable) {
                $isJson = false;
                $parsedBody = $rawBody;
            }
        }

        $isBinary = !$isJson
            && !str_contains($contentType, 'text')
            && !str_contains($contentType, 'event-stream');

        return response()->json([
            'status_code' => $response->status(),
            'content_type' => $contentType,
            'body' => $isBinary ? null : $parsedBody,
            'body_raw' => $isBinary ? null : (is_string($parsedBody) ? $parsedBody : null),
            'body_base64' => $isBinary ? base64_encode($rawBody) : null,
            'is_json' => $isJson && is_array($parsedBody),
            'is_binary' => $isBinary,
            'duration_ms' => $durationMs,
        ]);
    }

    private function sendWithOptionalBody($client, string $verb, string $url, mixed $body)
    {
        $hasBody = $body !== null && $body !== [] && $body !== '';

        if ($hasBody) {
            $payload = is_array($body) ? $body : [];

            return $client->withHeaders(['Content-Type' => 'application/json'])
                ->{$verb}($url, $payload);
        }

        return $client->{$verb}($url);
    }

    private function isAllowedPath(string $path): bool
    {
        $publicPaths = ['/app-logo', '/download-plugins', '/get-metadata'];

        if (in_array($path, $publicPaths, true)) {
            return true;
        }

        if (str_starts_with($path, '/brand-asset/')) {
            return true;
        }

        if (str_starts_with($path, '/api/')) {
            return true;
        }

        return false;
    }
}
