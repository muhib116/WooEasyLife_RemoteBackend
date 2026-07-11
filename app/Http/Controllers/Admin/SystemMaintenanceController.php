<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class SystemMaintenanceController extends Controller
{
    /**
     * @var array<string, array{label: string, description: string, commands: list<string>}>
     */
    private const ACTIONS = [
        'all' => [
            'label' => 'Clear all caches',
            'description' => 'Runs optimize:clear (cache, config, route, view, compiled)',
            'commands' => ['optimize:clear'],
        ],
        'cache' => [
            'label' => 'Application cache',
            'description' => 'php artisan cache:clear',
            'commands' => ['cache:clear'],
        ],
        'config' => [
            'label' => 'Config cache',
            'description' => 'php artisan config:clear',
            'commands' => ['config:clear'],
        ],
        'route' => [
            'label' => 'Route cache',
            'description' => 'php artisan route:clear',
            'commands' => ['route:clear'],
        ],
        'view' => [
            'label' => 'View cache',
            'description' => 'php artisan view:clear',
            'commands' => ['view:clear'],
        ],
        'optimize' => [
            'label' => 'Optimize',
            'description' => 'Rebuild config/route caches for production',
            'commands' => ['optimize'],
        ],
        'storage_link' => [
            'label' => 'Storage link',
            'description' => 'php artisan storage:link',
            'commands' => ['storage:link'],
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('Maintenance/Index', [
            'initialStatus' => $this->statusPayload(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json($this->statusPayload());
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', array_keys(self::ACTIONS))],
        ]);

        $action = $validated['action'];
        $definition = self::ACTIONS[$action];
        $lines = [];
        $failed = false;

        Log::info('System maintenance action started.', [
            'action' => $action,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            if ($action === 'storage_link') {
                return $this->runStorageLink($request, $definition);
            }

            foreach ($definition['commands'] as $command) {
                $output = new BufferedOutput();
                $exitCode = Artisan::call($command, [], $output);
                $body = trim($output->fetch());
                $lines[] = "[{$command}] ".($body !== '' ? $body : ($exitCode === 0 ? 'ok' : "failed (exit {$exitCode})"));

                if ($exitCode !== 0) {
                    $failed = true;
                    break;
                }
            }

            $payload = [
                'success' => ! $failed,
                'message' => $failed
                    ? "{$definition['label']} finished with errors."
                    : "{$definition['label']} completed.",
                'output' => implode("\n", $lines),
                'status' => $this->statusPayload(),
            ];

            Log::info('System maintenance action finished.', [
                'action' => $action,
                'success' => ! $failed,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json($payload, $failed ? 500 : 200);
        } catch (Throwable $e) {
            Log::error('System maintenance action failed.', [
                'action' => $action,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "{$definition['label']} failed: ".$e->getMessage(),
                'output' => $e->getMessage(),
                'status' => $this->statusPayload(),
            ], 500);
        }
    }

    /**
     * @param  array{label: string, description: string, commands: list<string>}  $definition
     */
    private function runStorageLink(Request $request, array $definition): JsonResponse
    {
        $link = public_path('storage');
        $alreadyLinked = is_link($link) || File::exists($link);

        if ($alreadyLinked) {
            Log::info('System maintenance storage link skipped (already exists).', [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Storage link already exists.',
                'output' => '[storage:link] already exists at '.$link,
                'status' => $this->statusPayload(),
            ]);
        }

        $output = new BufferedOutput();
        $exitCode = Artisan::call('storage:link', [], $output);
        $body = trim($output->fetch());
        $line = '[storage:link] '.($body !== '' ? $body : ($exitCode === 0 ? 'ok' : "failed (exit {$exitCode})"));
        $success = $exitCode === 0;

        Log::info('System maintenance storage link finished.', [
            'success' => $success,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => $success,
            'message' => $success
                ? 'Storage symlink created.'
                : 'Storage link failed. Check filesystem permissions.',
            'output' => $line,
            'status' => $this->statusPayload(),
        ], $success ? 200 : 500);
    }

    /**
     * @return array{
     *     storage_link_exists: bool,
     *     storage_link_path: string,
     *     public_storage_path: string,
     *     app_env: string,
     *     app_debug: bool,
     *     actions: list<array{key: string, label: string, description: string}>
     * }
     */
    private function statusPayload(): array
    {
        $link = public_path('storage');
        $actions = [];

        foreach (self::ACTIONS as $key => $definition) {
            $actions[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
            ];
        }

        return [
            'storage_link_exists' => is_link($link) || File::exists($link),
            'storage_link_path' => $link,
            'public_storage_path' => storage_path('app/public'),
            'app_env' => (string) config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'actions' => $actions,
        ];
    }
}
