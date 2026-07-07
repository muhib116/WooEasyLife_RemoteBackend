<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class DeployController extends Controller
{
    /**
     * Production deploy — run after uploading new code (no terminal required).
     *
     * Visit: GET /deploy/{DEPLOY_SECRET}
     */
    public function deploy(string $secret): JsonResponse
    {
        if (! $this->isAuthorized($secret)) {
            abort(404);
        }

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
     * Visit: GET /deploy/{DEPLOY_SECRET}/setup
     */
    public function setup(string $secret): JsonResponse
    {
        if (! $this->isAuthorized($secret)) {
            abort(404);
        }

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
            'message' => 'Setup complete. Run composer install via SSH/cPanel terminal if vendor/ is missing.',
            'results' => $results,
        ]);
    }

    private function isAuthorized(string $secret): bool
    {
        $deploySecret = (string) config('app.deploy_secret');

        return filled($deploySecret) && hash_equals($deploySecret, $secret);
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
