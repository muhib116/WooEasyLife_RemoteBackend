<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class DeployController extends Controller
{
    public function setup(string $secret): JsonResponse
    {
        if (! $this->isAuthorized($secret)) {
            abort(404);
        }

        $results = [];

        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
            $results['key:generate'] = trim(Artisan::output()) ?: 'ok';
        } else {
            $results['key:generate'] = 'skipped (APP_KEY already set)';
        }

        Artisan::call('migrate', ['--force' => true]);
        $results['migrate'] = trim(Artisan::output()) ?: 'ok';

        Artisan::call('db:seed', ['--force' => true]);
        $results['db:seed'] = trim(Artisan::output()) ?: 'ok';

        Artisan::call('storage:link');
        $results['storage:link'] = trim(Artisan::output()) ?: 'ok';

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::call($command);
            $results[$command] = trim(Artisan::output()) ?: 'ok';
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
        $deploySecret = (string) env('DEPLOY_SECRET', config('app.deploy_secret'));

        return filled($deploySecret) && hash_equals($deploySecret, $secret);
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
