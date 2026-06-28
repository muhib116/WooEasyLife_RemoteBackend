<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Models\User;
use App\Services\SubscriptionAlertService;
use Illuminate\Console\Command;

class CheckSubscriptionAlertsCommand extends Command
{
    protected $signature = 'subscriptions:check-alerts
                            {--user-id= : Scan a single merchant by ID}
                            {--chunk=100 : Merchants processed per batch}
                            {--limit=50 : Maximum rows to print in the report table}';

    protected $description = 'Scan merchant subscriptions and log lifecycle alerts (quota, expiry, pending payments)';

    public function handle(SubscriptionAlertService $alertService): int
    {
        $logged = 0;
        $feed = collect();
        $chunkSize = max(1, (int) $this->option('chunk'));
        $userId = $this->option('user-id');

        $scanUser = function (User $user) use ($alertService, &$logged, &$feed) {
            $tokens = AccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->get();

            foreach ($tokens as $token) {
                foreach ($alertService->collectAlerts($user, $token) as $alert) {
                    $domain = app(\App\Services\DomainNormalizer::class)->normalize($token->domain);
                    $alertService->logAlert($user, $domain, $alert);
                    $logged++;

                    $feed->push([
                        ...$alert,
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'domain' => $domain,
                    ]);
                }
            }
        };

        if ($userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                $this->error("Merchant #{$userId} not found.");

                return self::FAILURE;
            }

            $scanUser($user);
        } else {
            User::query()
                ->where('role', 'user')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($users) use ($scanUser) {
                    foreach ($users as $user) {
                        $scanUser($user);
                    }
                });
        }

        if ($feed->isEmpty()) {
            $this->info('No subscription alerts found.');

            return self::SUCCESS;
        }

        $summary = $alertService->summarizeAdminFeed($feed);
        $this->newLine();
        $this->info('Alert summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total alerts', $summary['total']],
                ['Danger', $summary['danger']],
                ['Warning', $summary['warning']],
                ['Info', $summary['info']],
            ]
        );

        $limit = max(1, (int) $this->option('limit'));
        $rows = $feed->take($limit)->map(function (array $alert) {
            return [
                $alert['severity'] ?? '-',
                $alert['type'] ?? '-',
                $alert['user_id'] ?? '-',
                $alert['user_name'] ?? '-',
                $alert['domain'] ?? '-',
                $alert['message'] ?? '-',
            ];
        })->all();

        $this->newLine();
        $this->info("Logged {$logged} alert(s). Showing up to {$limit}:");
        $this->table(
            ['Severity', 'Type', 'User ID', 'Merchant', 'Domain', 'Message'],
            $rows
        );

        if ($feed->count() > $limit) {
            $remaining = $feed->count() - $limit;
            $this->comment("{$remaining} additional alert(s) not shown. Increase --limit to see more.");
        }

        return self::SUCCESS;
    }
}
