<?php

namespace App\Console\Commands;

use App\Services\DatabaseImportService;
use Illuminate\Console\Command;

class ImportDatabase extends Command
{
    protected $signature = 'database:import {importId : The import session identifier}';

    protected $description = 'Import a SQL dump file into the database';

    public function handle(DatabaseImportService $importService): int
    {
        $importId = (string) $this->argument('importId');

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $importService->runImport($importId);

        $status = $importService->getStatus($importId);

        if (($status['status'] ?? null) === 'failed') {
            $this->error($status['error'] ?? 'Database import failed.');

            return self::FAILURE;
        }

        $this->info('Database import completed successfully.');

        return self::SUCCESS;
    }
}
