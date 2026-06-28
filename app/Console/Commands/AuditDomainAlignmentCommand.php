<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DomainAlignmentAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditDomainAlignmentCommand extends Command
{
    protected $signature = 'domains:audit
                            {--user-id= : Audit a single merchant by ID}
                            {--chunk=100 : Merchants processed per batch}
                            {--severity= : Filter by severity (high, medium, low)}
                            {--type= : Filter by issue type}
                            {--limit=50 : Maximum rows to print in the report table}';

    protected $description = 'Audit legacy domain string mismatches between licenses and plans (read-only, safe for live)';

    public function handle(DomainAlignmentAuditService $auditService): int
    {
        $issues = collect();
        $chunkSize = max(1, (int) $this->option('chunk'));
        $userId = $this->option('user-id');

        $collectForUser = function (User $user) use ($auditService, &$issues) {
            foreach ($auditService->auditUser($user) as $issue) {
                $issues->push($issue);
            }
        };

        if ($userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                $this->error("Merchant #{$userId} not found.");

                return self::FAILURE;
            }

            $collectForUser($user);
        } else {
            User::query()
                ->where('role', 'user')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($users) use ($collectForUser) {
                    foreach ($users as $user) {
                        $collectForUser($user);
                    }
                });
        }

        $issues = $this->applyFilters($issues);

        if ($issues->isEmpty()) {
            $this->info('No domain alignment issues found.');

            return self::SUCCESS;
        }

        $summary = $auditService->summarize($issues);
        $this->newLine();
        $this->info('Issue summary:');
        $this->table(
            ['Type', 'Count'],
            collect($summary)->map(fn ($count, $type) => [$type, $count])->values()->all()
        );

        $limit = max(1, (int) $this->option('limit'));
        $rows = $issues->take($limit)->map(function (array $issue) {
            return [
                $issue['severity'] ?? '-',
                $issue['type'] ?? '-',
                $issue['user_id'] ?? '-',
                $issue['user_name'] ?? '-',
                $issue['token_id'] ?? '-',
                $issue['token_domain'] ?? ($issue['normalized_domain'] ?? '-'),
                $issue['package_domain'] ?? '-',
                $issue['message'] ?? '-',
            ];
        })->all();

        $this->newLine();
        $this->info("Showing up to {$limit} issue(s):");
        $this->table(
            ['Severity', 'Type', 'User ID', 'Merchant', 'Token ID', 'Token Domain', 'Plan Domain', 'Message'],
            $rows
        );

        if ($issues->count() > $limit) {
            $remaining = $issues->count() - $limit;
            $this->comment("{$remaining} additional issue(s) not shown. Increase --limit to see more.");
        }

        $this->newLine();
        $this->comment('This command is read-only. Run php artisan domains:normalize to repair legacy domain strings.');

        return self::SUCCESS;
    }

    private function applyFilters(Collection $issues): Collection
    {
        if ($severity = $this->option('severity')) {
            $issues = $issues->filter(
                fn (array $issue) => ($issue['severity'] ?? null) === $severity
            );
        }

        if ($type = $this->option('type')) {
            $issues = $issues->filter(
                fn (array $issue) => ($issue['type'] ?? null) === $type
            );
        }

        return $issues->values();
    }
}
