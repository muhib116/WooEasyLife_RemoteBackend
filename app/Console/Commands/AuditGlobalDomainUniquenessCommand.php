<?php

namespace App\Console\Commands;

use App\Services\DomainAvailabilityService;
use Illuminate\Console\Command;

class AuditGlobalDomainUniquenessCommand extends Command
{
    protected $signature = 'domains:audit-global-uniqueness';

    protected $description = 'Audit cross-merchant domain conflicts before applying global unique(domain) on websites';

    public function handle(DomainAvailabilityService $domainAvailability): int
    {
        $conflicts = $domainAvailability->findCrossUserConflicts();

        if ($conflicts === []) {
            $this->info('No cross-merchant domain conflicts found.');

            return self::SUCCESS;
        }

        $this->error('Cross-merchant domain conflicts detected:');
        $this->newLine();

        $rows = [];
        foreach ($conflicts as $conflict) {
            $sourceSummary = collect($conflict['sources'])
                ->map(fn (array $ids, string $table) => $table . ': ' . implode(', ', $ids))
                ->implode('; ');

            $rows[] = [
                $conflict['domain'],
                implode(', ', array_map(fn (int $id) => "#{$id}", $conflict['user_ids'])),
                $sourceSummary,
            ];
        }

        $this->table(['Domain', 'Merchant IDs', 'Sources'], $rows);
        $this->newLine();
        $this->comment('Resolve conflicts via admin website removal on duplicate merchants, then re-run this command.');
        $this->comment('Run php artisan domains:normalize before auditing if domains differ by casing or scheme.');

        return self::FAILURE;
    }
}
