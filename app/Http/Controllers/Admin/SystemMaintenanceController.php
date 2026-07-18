<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogLearningInsight;
use App\Services\BlogAi\BlogLearningService;
use App\Services\Seo\GoogleSearchConsoleClient;
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
     * Safe, admin-runnable Artisan actions (no migrate:fresh / db:wipe / import-with-id).
     *
     * @var array<string, array{
     *     label: string,
     *     description: string,
     *     commands: list<string|array{0: string, 1?: array<string, mixed>}>,
     *     group?: string,
     *     include_in_run_all?: bool,
     *     is_batch?: bool
     * }>
     */
    private const ACTIONS = [
        'run_all' => [
            'label' => 'Run everything',
            'description' => 'Runs every maintenance command below (skips notify / Steadfast / optimize conflicts). May take several minutes.',
            'commands' => [],
            'group' => 'meta',
            'is_batch' => true,
            'include_in_run_all' => false,
        ],
        'migrate' => [
            'label' => 'Run pending migrations',
            'description' => 'php artisan migrate --force — apply pending DB schema (run this before blog learning)',
            'commands' => [
                ['migrate', ['--force' => true]],
            ],
            'group' => 'cache',
        ],
        'all' => [
            'label' => 'Clear all caches',
            'description' => 'php artisan optimize:clear (cache, config, route, view, compiled)',
            'commands' => ['optimize:clear'],
            'group' => 'cache',
        ],
        'cache' => [
            'label' => 'Application cache',
            'description' => 'php artisan cache:clear',
            'commands' => ['cache:clear'],
            'group' => 'cache',
        ],
        'config' => [
            'label' => 'Config cache',
            'description' => 'php artisan config:clear',
            'commands' => ['config:clear'],
            'group' => 'cache',
        ],
        'route' => [
            'label' => 'Route cache',
            'description' => 'php artisan route:clear',
            'commands' => ['route:clear'],
            'group' => 'cache',
        ],
        'view' => [
            'label' => 'View cache',
            'description' => 'php artisan view:clear',
            'commands' => ['view:clear'],
            'group' => 'cache',
        ],
        'event_clear' => [
            'label' => 'Event cache clear',
            'description' => 'php artisan event:clear',
            'commands' => ['event:clear'],
            'group' => 'cache',
        ],
        'optimize' => [
            'label' => 'Optimize (rebuild caches)',
            'description' => 'php artisan optimize — rebuild config/route caches for production',
            'commands' => ['optimize'],
            'group' => 'cache',
            // Avoid clear-then-optimize in the same batch.
            'include_in_run_all' => false,
        ],
        'storage_link' => [
            'label' => 'Storage link',
            'description' => 'php artisan storage:link',
            'commands' => ['storage:link'],
            'group' => 'cache',
        ],
        'queue_restart' => [
            'label' => 'Queue restart',
            'description' => 'php artisan queue:restart — signal workers to restart after current job',
            'commands' => ['queue:restart'],
            'group' => 'cache',
        ],
        'schedule_clear_cache' => [
            'label' => 'Schedule mutex clear',
            'description' => 'php artisan schedule:clear-cache',
            'commands' => ['schedule:clear-cache'],
            'group' => 'cache',
        ],
        'auth_clear_resets' => [
            'label' => 'Clear password resets',
            'description' => 'php artisan auth:clear-resets',
            'commands' => ['auth:clear-resets'],
            'group' => 'cache',
        ],

        'blog_learning_insights' => [
            'label' => 'Blog learning insights',
            'description' => 'php artisan blog:build-learning-insights — analytics rollup + AI learning snapshot',
            'commands' => ['blog:build-learning-insights'],
            'group' => 'blog',
        ],
        'blog_analytics_rollup' => [
            'label' => 'Blog analytics rollup only',
            'description' => 'php artisan blog:build-learning-insights --rollup-only',
            'commands' => [
                ['blog:build-learning-insights', ['--rollup-only' => true]],
            ],
            'group' => 'blog',
            // Covered by full learning insights in run_all.
            'include_in_run_all' => false,
        ],
        'seo_weekly_report' => [
            'label' => 'SEO weekly report',
            'description' => 'php artisan seo:weekly-report',
            'commands' => ['seo:weekly-report'],
            'group' => 'blog',
        ],
        'seo_gsc_status' => [
            'label' => 'GSC status / probe',
            'description' => 'php artisan seo:gsc-status --probe — check Google Search Console OAuth + fetch sample queries',
            'commands' => [
                ['seo:gsc-status', ['--probe' => true]],
            ],
            'group' => 'blog',
            'include_in_run_all' => false,
        ],

        'subscriptions_apply_expiry' => [
            'label' => 'Apply subscription expiry',
            'description' => 'php artisan subscriptions:apply-expiry',
            'commands' => ['subscriptions:apply-expiry'],
            'group' => 'subscriptions',
        ],
        'subscriptions_check_alerts' => [
            'label' => 'Check subscription alerts',
            'description' => 'php artisan subscriptions:check-alerts',
            'commands' => ['subscriptions:check-alerts'],
            'group' => 'subscriptions',
        ],
        'subscriptions_notify' => [
            'label' => 'Notify subscription alerts',
            'description' => 'php artisan subscriptions:notify — sends email/SMS/WhatsApp (side effects)',
            'commands' => ['subscriptions:notify'],
            'group' => 'subscriptions',
            'include_in_run_all' => false,
        ],

        'domains_audit' => [
            'label' => 'Domains audit',
            'description' => 'php artisan domains:audit (read-only)',
            'commands' => ['domains:audit'],
            'group' => 'domains',
        ],
        'domains_audit_global' => [
            'label' => 'Domains global uniqueness audit',
            'description' => 'php artisan domains:audit-global-uniqueness',
            'commands' => ['domains:audit-global-uniqueness'],
            'group' => 'domains',
        ],
        'domains_normalize' => [
            'label' => 'Normalize domains',
            'description' => 'php artisan domains:normalize',
            'commands' => ['domains:normalize'],
            'group' => 'domains',
        ],
        'websites_backfill' => [
            'label' => 'Backfill websites',
            'description' => 'php artisan websites:backfill',
            'commands' => ['websites:backfill'],
            'group' => 'domains',
        ],

        'courier_retry_webhooks' => [
            'label' => 'Retry courier webhook forwards',
            'description' => 'php artisan courier:retry-webhook-forwards',
            'commands' => ['courier:retry-webhook-forwards'],
            'group' => 'ops',
        ],
        'order_intelligence_reindex' => [
            'label' => 'Reindex order intelligence search',
            'description' => 'php artisan order-intelligence:reindex-search',
            'commands' => ['order-intelligence:reindex-search'],
            'group' => 'ops',
        ],
        'steadfast_refresh_curl' => [
            'label' => 'Refresh Steadfast curl',
            'description' => 'php artisan steadfast:refresh-curl — live login to Steadfast (side effects)',
            'commands' => ['steadfast:refresh-curl'],
            'group' => 'ops',
            'include_in_run_all' => false,
        ],
    ];

    /** @var array<string, string> */
    private const GROUP_LABELS = [
        'meta' => 'Batch',
        'cache' => 'Cache & framework',
        'blog' => 'Blog SEO & learning',
        'subscriptions' => 'Subscriptions',
        'domains' => 'Domains & websites',
        'ops' => 'Ops & integrations',
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

        @set_time_limit(600);

        Log::info('System maintenance action started.', [
            'action' => $action,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        try {
            if ($action === 'run_all') {
                return $this->runAllActions($request, $definition);
            }

            if ($action === 'storage_link') {
                return $this->runStorageLink($request, $definition);
            }

            return $this->runCommandSet($request, $action, $definition);
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
     * @param  array{label: string, description: string, commands: list<mixed>, group?: string}  $batchDefinition
     */
    private function runAllActions(Request $request, array $batchDefinition): JsonResponse
    {
        $allLines = [];
        $failedActions = [];
        $ran = 0;
        $skipped = 0;

        foreach (self::ACTIONS as $key => $definition) {
            if (! empty($definition['is_batch'])) {
                continue;
            }
            if (array_key_exists('include_in_run_all', $definition) && $definition['include_in_run_all'] === false) {
                $allLines[] = "[{$key}] skipped (excluded from batch)";
                $skipped++;

                continue;
            }

            if ($key === 'storage_link') {
                $link = public_path('storage');
                if (is_link($link) || File::exists($link)) {
                    $allLines[] = '[storage:link] already exists — skipped';
                    $ran++;

                    continue;
                }
            }

            $result = $this->executeCommands($definition['commands'] ?? []);
            $allLines[] = "—— {$definition['label']} ({$key}) ——";
            foreach ($result['lines'] as $line) {
                $allLines[] = $line;
            }
            $ran++;

            if ($result['failed']) {
                $failedActions[] = $key;
                $allLines[] = "[{$key}] finished with errors — continuing batch";
            }
        }

        $success = $failedActions === [];
        $message = $success
            ? "Run everything completed ({$ran} actions, {$skipped} skipped)."
            : 'Run everything finished with errors on: '.implode(', ', $failedActions)." ({$ran} actions attempted).";

        Log::info('System maintenance run_all finished.', [
            'success' => $success,
            'ran' => $ran,
            'skipped' => $skipped,
            'failed' => $failedActions,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => $success,
            'message' => $message,
            'output' => implode("\n", $allLines),
            'failed_actions' => $failedActions,
            'status' => $this->statusPayload(),
        ], 200);
    }

    /**
     * @param  array{label: string, description: string, commands: list<mixed>}  $definition
     */
    private function runCommandSet(Request $request, string $action, array $definition): JsonResponse
    {
        $result = $this->executeCommands($definition['commands'] ?? []);
        $failed = $result['failed'];

        $payload = [
            'success' => ! $failed,
            'message' => $failed
                ? "{$definition['label']} finished with errors."
                : "{$definition['label']} completed.",
            'output' => implode("\n", $result['lines']),
            'status' => $this->statusPayload(),
        ];

        Log::info('System maintenance action finished.', [
            'action' => $action,
            'success' => ! $failed,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json($payload, $failed ? 500 : 200);
    }

    /**
     * @param  list<string|array{0: string, 1?: array<string, mixed>}>  $commands
     * @return array{failed: bool, lines: list<string>}
     */
    private function executeCommands(array $commands): array
    {
        $lines = [];
        $failed = false;

        foreach ($commands as $command) {
            if (is_array($command)) {
                $name = (string) ($command[0] ?? '');
                $params = is_array($command[1] ?? null) ? $command[1] : [];
            } else {
                $name = (string) $command;
                $params = [];
            }

            if ($name === '') {
                continue;
            }

            $label = $params === []
                ? $name
                : $name.' '.collect($params)->map(function ($value, $key) {
                    if ($value === true) {
                        return (string) $key;
                    }

                    return $key.'='.$value;
                })->implode(' ');

            try {
                $output = new BufferedOutput;
                $exitCode = Artisan::call($name, $params, $output);
                $body = trim($output->fetch());
                $lines[] = "[{$label}] ".($body !== '' ? $body : ($exitCode === 0 ? 'ok' : "failed (exit {$exitCode})"));

                if ($exitCode !== 0) {
                    $failed = true;
                    break;
                }
            } catch (Throwable $e) {
                $lines[] = "[{$label}] exception: ".$e->getMessage();
                $failed = true;
                break;
            }
        }

        return compact('failed', 'lines');
    }

    /**
     * @param  array{label: string, description: string, commands: list<mixed>}  $definition
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

        $output = new BufferedOutput;
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
     * @return array<string, mixed>
     */
    private function statusPayload(): array
    {
        $link = public_path('storage');
        $actions = [];
        $groups = [];

        foreach (self::ACTIONS as $key => $definition) {
            $group = $definition['group'] ?? 'cache';
            $actions[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'group' => $group,
                'include_in_run_all' => $definition['include_in_run_all'] ?? true,
                'is_batch' => (bool) ($definition['is_batch'] ?? false),
            ];
            if (! isset($groups[$group])) {
                $groups[$group] = self::GROUP_LABELS[$group] ?? ucfirst($group);
            }
        }

        $learning = null;
        try {
            $insight = BlogLearningInsight::latestGlobal();
            if ($insight) {
                $learning = [
                    'generated_at' => optional($insight->generated_at)?->toIso8601String(),
                    'summary_bn' => $insight->summary_bn,
                    'posts_analyzed' => $insight->posts_analyzed,
                    'events_analyzed' => $insight->events_analyzed,
                    'next_post_ideas' => array_slice($insight->payload_json['next_post_ideas'] ?? [], 0, 5),
                ];
            }
        } catch (Throwable) {
            $learning = null;
        }

        $rankOpportunities = [
            'configured' => false,
            'table_ready' => false,
            'refreshed_at' => null,
            'summary' => [],
            'items' => [],
        ];
        try {
            $rankOpportunities = app(BlogLearningService::class)
                ->rankOpportunitiesForAdmin(30);
        } catch (Throwable) {
            // Keep empty payload — maintenance page must not break if learning service fails.
        }

        $gscStatus = [
            'site_url' => null,
            'has_site_url' => false,
            'has_client_id' => false,
            'has_client_secret' => false,
            'has_refresh_token' => false,
            'has_static_access_token' => false,
            'auth_mode' => 'missing',
            'ready' => false,
            'can_connect' => false,
            'connect_url' => null,
            'disconnect_url' => null,
            'refresh_token_source' => null,
        ];
        try {
            $gscStatus = app(GoogleSearchConsoleClient::class)->configurationStatus();
        } catch (Throwable) {
            // ignore
        }

        return [
            'storage_link_exists' => is_link($link) || File::exists($link),
            'storage_link_path' => $link,
            'public_storage_path' => storage_path('app/public'),
            'app_env' => (string) config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'actions' => $actions,
            'groups' => $groups,
            'blog_learning' => $learning,
            'rank_opportunities' => $rankOpportunities,
            'gsc_status' => $gscStatus,
        ];
    }
}
