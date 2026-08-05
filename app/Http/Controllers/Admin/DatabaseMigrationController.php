<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class DatabaseMigrationController extends Controller
{
    /**
     * Allowlisted seeders only — never expose full DatabaseSeeder / DemoDataSeeder.
     *
     * @var array<string, class-string>
     */
    private const ALLOWED_SEEDERS = [
        'BlogPostSeeder' => \Database\Seeders\BlogPostSeeder::class,
        'WiseKnowledgeSeeder' => \Database\Seeders\WiseKnowledgeSeeder::class,
    ];

    public function index(): Response
    {
        return Inertia::render('Migrations/Index', [
            'initialStatus' => $this->statusPayload(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json($this->statusPayload());
    }

    public function migrate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pretend' => ['sometimes', 'boolean'],
        ]);

        $pretend = (bool) ($validated['pretend'] ?? false);

        Log::info('Database migrate started from admin UI.', [
            'pretend' => $pretend,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $output = new BufferedOutput();
            $exitCode = Artisan::call('migrate', [
                '--force' => true,
                '--pretend' => $pretend,
            ], $output);

            Log::info('Database migrate finished from admin UI.', [
                'pretend' => $pretend,
                'exit_code' => $exitCode,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $pretend
                    ? 'Dry-run completed (no database changes).'
                    : ($exitCode === 0 ? 'Migrations ran successfully.' : 'Migration command finished with errors.'),
                'output' => trim($output->fetch()),
                'status' => $this->statusPayload(),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            Log::error('Database migrate failed from admin UI.', [
                'pretend' => $pretend,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Migration failed: '.$e->getMessage(),
                'output' => $e->getMessage(),
                'status' => $this->statusPayload(),
            ], 500);
        }
    }

    public function rollback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'pretend' => ['sometimes', 'boolean'],
        ]);

        $step = (int) ($validated['step'] ?? 1);
        $pretend = (bool) ($validated['pretend'] ?? false);

        Log::warning('Database rollback started from admin UI.', [
            'step' => $step,
            'pretend' => $pretend,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $output = new BufferedOutput();
            $exitCode = Artisan::call('migrate:rollback', [
                '--force' => true,
                '--step' => $step,
                '--pretend' => $pretend,
            ], $output);

            Log::warning('Database rollback finished from admin UI.', [
                'step' => $step,
                'exit_code' => $exitCode,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $pretend
                    ? "Dry-run rollback of {$step} batch step(s) completed."
                    : ($exitCode === 0
                        ? "Rolled back {$step} migration step(s)."
                        : 'Rollback finished with errors.'),
                'output' => trim($output->fetch()),
                'status' => $this->statusPayload(),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            Log::error('Database rollback failed from admin UI.', [
                'step' => $step,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rollback failed: '.$e->getMessage(),
                'output' => $e->getMessage(),
                'status' => $this->statusPayload(),
            ], 500);
        }
    }

    /**
     * Run an allowlisted database seeder from the admin UI.
     */
    public function seed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seeder' => ['required', 'string', Rule::in(array_keys(self::ALLOWED_SEEDERS))],
        ]);

        $key = $validated['seeder'];
        $class = self::ALLOWED_SEEDERS[$key];

        Log::info('Database seeder started from admin UI.', [
            'seeder' => $key,
            'class' => $class,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            $output = new BufferedOutput();
            $exitCode = Artisan::call('db:seed', [
                '--class' => $class,
                '--force' => true,
            ], $output);

            Log::info('Database seeder finished from admin UI.', [
                'seeder' => $key,
                'exit_code' => $exitCode,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0
                    ? "Seeder “{$key}” ran successfully."
                    : "Seeder “{$key}” finished with errors.",
                'output' => trim($output->fetch()),
                'status' => $this->statusPayload(),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            Log::error('Database seeder failed from admin UI.', [
                'seeder' => $key,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Seeder failed: '.$e->getMessage(),
                'output' => $e->getMessage(),
                'status' => $this->statusPayload(),
            ], 500);
        }
    }

    /**
     * @return array{
     *     pending_count: int,
     *     ran_count: int,
     *     latest_batch: int|null,
     *     repository_ready: bool,
     *     pending: list<array{name: string, file: string}>,
     *     ran: list<array{name: string, batch: int|null}>,
     *     connection: string,
     *     seeders: list<array{key: string, label: string, description: string}>
     * }
     */
    private function statusPayload(): array
    {
        $connection = (string) config('database.default');
        $migrator = app('migrator');
        $migrator->setConnection($connection);

        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $repositoryReady = false;
        $ranNames = [];
        $batches = [];

        try {
            $repositoryReady = Schema::connection($connection)->hasTable('migrations');
            if ($repositoryReady) {
                $ranNames = $migrator->getRepository()->getRan();
                $batches = $migrator->getRepository()->getMigrationBatches();
            }
        } catch (Throwable) {
            $repositoryReady = false;
        }

        $pending = [];
        $ran = [];

        foreach ($files as $name => $path) {
            if (in_array($name, $ranNames, true)) {
                $ran[] = [
                    'name' => $name,
                    'batch' => isset($batches[$name]) ? (int) $batches[$name] : null,
                ];
            } else {
                $pending[] = [
                    'name' => $name,
                    'file' => basename((string) $path),
                ];
            }
        }

        usort($ran, function (array $a, array $b) {
            $batchCmp = ($b['batch'] ?? 0) <=> ($a['batch'] ?? 0);
            if ($batchCmp !== 0) {
                return $batchCmp;
            }

            return strcmp($b['name'], $a['name']);
        });

        $latestBatch = $batches === [] ? null : max(array_map('intval', array_values($batches)));

        return [
            'pending_count' => count($pending),
            'ran_count' => count($ran),
            'latest_batch' => $latestBatch,
            'repository_ready' => $repositoryReady,
            'pending' => $pending,
            'ran' => $ran,
            'connection' => $connection,
            'seeders' => [
                [
                    'key' => 'BlogPostSeeder',
                    'label' => 'Seed SEO blogs',
                    'description' => 'Publishes 20 SEO blog posts (idempotent — safe to re-run). Does not touch users or demo data.',
                ],
                [
                    'key' => 'WiseKnowledgeSeeder',
                    'label' => 'Seed Wise knowledge',
                    'description' => 'Platform + regional scripts as drafts only (idempotent). Publish manually in Wise AI → Knowledge → Seeded review.',
                ],
            ],
        ];
    }
}
