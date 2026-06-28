<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Models\User;
use App\Services\SubscriptionAlertService;
use App\Services\SubscriptionNotificationService;
use Illuminate\Console\Command;

class NotifySubscriptionAlertsCommand extends Command
{
    protected $signature = 'subscriptions:notify
                            {--user-id= : Notify a single merchant by ID}
                            {--chunk=100 : Merchants processed per batch}
                            {--dry-run : Scan without sending notifications}';

    protected $description = 'Send subscription alert notifications to merchants (email, SMS, WhatsApp)';

    public function handle(
        SubscriptionAlertService $alertService,
        SubscriptionNotificationService $notificationService
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user-id');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $totals = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $notifyUser = function (User $user) use ($alertService, $notificationService, $dryRun, &$totals) {
            $tokens = AccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->get();

            foreach ($tokens as $token) {
                foreach ($alertService->collectAlerts($user, $token) as $alert) {
                    if ($dryRun) {
                        $this->line("[dry-run] {$user->email} — {$alert['type']}: {$alert['message']}");
                        $totals['skipped']++;

                        continue;
                    }

                    $result = $notificationService->notifyMerchant($user, $token, $alert);
                    $totals['sent'] += $result['sent'];
                    $totals['skipped'] += $result['skipped'];
                    $totals['failed'] += $result['failed'];
                }
            }
        };

        if ($userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                $this->error("Merchant #{$userId} not found.");

                return self::FAILURE;
            }

            $notifyUser($user);
        } else {
            User::query()
                ->where('role', 'user')
                ->where('status', true)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($users) use ($notifyUser) {
                    foreach ($users as $user) {
                        $notifyUser($user);
                    }
                });
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Notification dispatch complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Sent', $totals['sent']],
                ['Skipped', $totals['skipped']],
                ['Failed', $totals['failed']],
            ]
        );

        return self::SUCCESS;
    }
}
