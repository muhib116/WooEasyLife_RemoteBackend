<?php

namespace App\Console\Commands;

use App\WiseAi\Eval\EvalRunner;
use Illuminate\Console\Command;

class WiseEvalGoldensCommand extends Command
{
    protected $signature = 'wise:eval
                            {--only= : Run a single golden id (e.g. S1)}
                            {--json : Print machine-readable JSON}';

    protected $description = 'Run Wise AI situation goldens (S0–S9) against TurnRunner';

    public function handle(EvalRunner $runner): int
    {
        $only = $this->option('only');

        try {
            $report = $runner->run(onlyId: is_string($only) && $only !== '' ? $only : null);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $report['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Wise eval {$report['version']} · brain {$report['brain_version']}");
        $this->newLine();

        foreach ($report['results'] as $row) {
            $status = strtoupper((string) $row['status']);
            $line = "[{$row['id']}] {$status} — {$row['name']}";
            match ($row['status']) {
                'passed' => $this->line("<fg=green>{$line}</>"),
                'skipped' => $this->line("<fg=yellow>{$line}</>".(! empty($row['reason']) ? " ({$row['reason']})" : '')),
                default => $this->line("<fg=red>{$line}</>"),
            };
            if (($row['status'] ?? '') === 'failed') {
                foreach ($row['errors'] ?? [] as $err) {
                    $this->line("    · {$err}");
                }
                if (! empty($row['got'])) {
                    $this->line('    got: '.json_encode($row['got'], JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $this->newLine();
        $this->info("passed={$report['passed']} failed={$report['failed']} skipped={$report['skipped']}");

        return $report['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
