<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class DeployController extends Controller
{
    /**
     * Production deploy — run after uploading new code (no terminal required).
     *
     * POST /deploy
     * Header: X-Deploy-Secret: {DEPLOY_SECRET}
     * (also accepts Authorization: Bearer {DEPLOY_SECRET})
     */
    public function deploy(Request $request): JsonResponse
    {
        if (! $this->isAuthorized($request)) {
            abort(404);
        }

        Log::info('Deploy endpoint invoked.', [
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $results = [];

        $results['optimize:clear'] = $this->runArtisan('optimize:clear');
        $results['migrate'] = $this->runArtisan('migrate', ['--force' => true]);
        $results['storage:link'] = $this->runArtisan('storage:link', [], allowFailure: true);
        $results['optimize'] = $this->runArtisan('optimize');
        $results['order-intelligence:reindex-search'] = $this->runArtisan(
            'order-intelligence:reindex-search',
            [],
            allowFailure: true
        );
        $results['queue:restart'] = $this->runArtisan('queue:restart', [], allowFailure: true);

        return response()->json([
            'status' => 'success',
            'message' => 'Deploy complete. Upload vendor/ via FTP or run composer install if dependencies changed.',
            'results' => $results,
        ]);
    }

    /**
     * First-time server setup — migrations, seed, caches, permissions.
     *
     * Disabled unless DEPLOY_ALLOW_SETUP=true. Keep that false on live servers.
     *
     * POST /deploy/setup
     * Header: X-Deploy-Secret: {DEPLOY_SECRET}
     */
    public function setup(Request $request): JsonResponse
    {
        if (! $this->isAuthorized($request)) {
            abort(404);
        }

        if (! (bool) config('app.deploy_allow_setup')) {
            abort(404);
        }

        Log::warning('Deploy setup endpoint invoked (seed enabled).', [
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $results = [];

        if (empty(config('app.key'))) {
            $results['key:generate'] = $this->runArtisan('key:generate', ['--force' => true]);
        } else {
            $results['key:generate'] = 'skipped (APP_KEY already set)';
        }

        $results['migrate'] = $this->runArtisan('migrate', ['--force' => true]);
        $results['db:seed'] = $this->runArtisan('db:seed', ['--force' => true]);
        $results['storage:link'] = $this->runArtisan('storage:link', [], allowFailure: true);

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            $results[$command] = $this->runArtisan($command);
        }

        $results['permissions'] = $this->fixPermissions();

        return response()->json([
            'status' => 'success',
            'message' => 'Setup complete. Set DEPLOY_ALLOW_SETUP=false after first install.',
            'results' => $results,
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $deploySecret = (string) config('app.deploy_secret');

        if (! filled($deploySecret)) {
            return false;
        }

        $provided = $this->extractSecret($request);

        return filled($provided) && hash_equals($deploySecret, $provided);
    }

    private function extractSecret(Request $request): string
    {
        $header = (string) $request->header('X-Deploy-Secret', '');
        if ($header !== '') {
            return $header;
        }

        $authorization = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return (string) $request->input('secret', '');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(string $command, array $parameters = [], bool $allowFailure = false): string
    {
        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = trim(Artisan::output());

            if ($exitCode !== 0 && ! $allowFailure) {
                return $output !== '' ? $output : "failed (exit {$exitCode})";
            }

            if ($exitCode !== 0) {
                return $output !== '' ? "skipped: {$output}" : 'skipped';
            }

            return $output !== '' ? $output : 'ok';
        } catch (Throwable $e) {
            if ($allowFailure) {
                return 'skipped: '.$e->getMessage();
            }

            throw $e;
        }
    }

    /**
     * @return array<string, string>
     */
    private function fixPermissions(): array
    {
        $paths = [
            storage_path(),
            base_path('bootstrap/cache'),
        ];

        $results = [];

        foreach ($paths as $path) {
            $results[$path] = $this->chmodRecursive($path, 0775);
        }

        return $results;
    }

    private function chmodRecursive(string $path, int $mode): string
    {
        if (! is_dir($path)) {
            return 'missing';
        }

        @chmod($path, $mode);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            @chmod($item->getPathname(), $mode);
        }

        return 'ok';
    }
}
