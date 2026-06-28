<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DatabaseImportService
{
    private const CACHE_PREFIX = 'db_import:';

    private const CACHE_TTL_SECONDS = 3600;

    private const CHUNK_SIZE = 524288;

    private const PROGRESS_UPDATE_INTERVAL = 2097152;

    private const IMPORT_LOCK_KEY = 'db_import:active_lock';

    public function createImport(string $filePath, string $originalName): string
    {
        if (! is_file($filePath)) {
            throw new \InvalidArgumentException('Import file not found.');
        }

        $importId = (string) Str::uuid();

        $this->putStatus($importId, [
            'status' => 'pending',
            'phase' => 'import',
            'progress' => 0,
            'message' => 'Ready to import.',
            'error' => null,
            'file_path' => $filePath,
            'file_name' => $originalName,
            'bytes_processed' => 0,
            'bytes_total' => filesize($filePath) ?: 0,
            'started_at' => now()->timestamp,
            'completed_at' => null,
        ]);

        return $importId;
    }

    public function getStatus(string $importId): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$importId);
    }

    public function runImport(string $importId): void
    {
        $status = $this->getStatus($importId);

        if (! $status) {
            throw new \InvalidArgumentException('Import session not found.');
        }

        $filePath = $status['file_path'] ?? '';

        if (! is_file($filePath)) {
            $this->markFailed($importId, 'Import file no longer exists on the server.');

            return;
        }

        $this->updateStatus($importId, [
            'status' => 'importing',
            'phase' => 'import',
            'progress' => 0,
            'message' => 'Importing database...',
            'error' => null,
            'bytes_processed' => 0,
            'bytes_total' => filesize($filePath) ?: 0,
            'started_at' => now()->timestamp,
        ]);

        try {
            $this->importViaMysqlCli($filePath, function (int $bytesProcessed, int $bytesTotal) use ($importId) {
                $progress = $bytesTotal > 0
                    ? min(99, (int) round(($bytesProcessed / $bytesTotal) * 100))
                    : 0;

                $this->updateStatus($importId, [
                    'status' => 'importing',
                    'phase' => 'import',
                    'progress' => $progress,
                    'message' => 'Importing database...',
                    'bytes_processed' => $bytesProcessed,
                    'bytes_total' => $bytesTotal,
                ]);
            });

            $this->updateStatus($importId, [
                'status' => 'completed',
                'phase' => 'import',
                'progress' => 100,
                'message' => 'Database imported successfully.',
                'bytes_processed' => filesize($filePath) ?: 0,
                'bytes_total' => filesize($filePath) ?: 0,
                'completed_at' => now()->timestamp,
            ]);
        } catch (\Throwable $exception) {
            $this->markFailed($importId, $exception->getMessage());
        } finally {
            $this->cleanupUploadedImport($importId);
            $this->releaseImportLock($importId);
        }
    }

    public function hasActiveImport(): bool
    {
        $activeImportId = Cache::get(self::IMPORT_LOCK_KEY);

        if (! $activeImportId) {
            return false;
        }

        $status = $this->getStatus($activeImportId);

        if (! $status) {
            Cache::forget(self::IMPORT_LOCK_KEY);

            return false;
        }

        return in_array($status['status'] ?? null, ['queued', 'importing'], true);
    }

    public function dispatchBackgroundImport(string $importId): void
    {
        if ($this->hasActiveImport()) {
            throw new \RuntimeException('Another database import is already in progress.');
        }

        Cache::put(self::IMPORT_LOCK_KEY, $importId, self::CACHE_TTL_SECONDS);

        $this->updateStatus($importId, [
            'status' => 'queued',
            'phase' => 'import',
            'message' => 'Import queued...',
        ]);

        $phpBinary = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $id = escapeshellarg($importId);

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B {$phpBinary} {$artisan} database:import {$id}", 'r'));

            return;
        }

        exec("{$phpBinary} {$artisan} database:import {$id} > /dev/null 2>&1 &");
    }

    private function importViaMysqlCli(string $filePath, callable $onProgress): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $dbHost = $connection['host'] ?? '127.0.0.1';
        $dbPort = (string) ($connection['port'] ?? 3306);
        $dbUser = $connection['username'] ?? '';
        $dbPass = $connection['password'] ?? '';
        $dbName = $connection['database'] ?? '';

        $command = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new \RuntimeException('Failed to start the MySQL import process.');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            $this->closeProcess($process, $pipes);
            throw new \RuntimeException('Failed to read the SQL file.');
        }

        $totalSize = filesize($filePath) ?: 0;
        $bytesSent = 0;
        $lastProgressUpdate = 0;

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);

                if ($chunk === false) {
                    throw new \RuntimeException('Failed while reading the SQL file.');
                }

                if ($chunk === '') {
                    break;
                }

                $written = 0;
                $chunkLength = strlen($chunk);

                while ($written < $chunkLength) {
                    $result = fwrite($pipes[0], substr($chunk, $written));

                    if ($result === false) {
                        throw new \RuntimeException('Failed while sending SQL data to MySQL.');
                    }

                    $written += $result;
                }

                $bytesSent += $chunkLength;

                if (($bytesSent - $lastProgressUpdate) >= self::PROGRESS_UPDATE_INTERVAL || $bytesSent >= $totalSize) {
                    $onProgress($bytesSent, $totalSize);
                    $lastProgressUpdate = $bytesSent;
                }
            }
        } finally {
            fclose($handle);
            fclose($pipes[0]);
        }

        $stderr = stream_get_contents($pipes[2]) ?: '';
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $message = trim($stderr) ?: trim($stdout) ?: 'Unknown MySQL import error.';

            throw new \RuntimeException($message);
        }
    }

    private function closeProcess($process, array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($process)) {
            proc_close($process);
        }
    }

    private function releaseImportLock(string $importId): void
    {
        if (Cache::get(self::IMPORT_LOCK_KEY) === $importId) {
            Cache::forget(self::IMPORT_LOCK_KEY);
        }
    }

    private function markFailed(string $importId, string $message): void
    {
        $this->updateStatus($importId, [
            'status' => 'failed',
            'phase' => 'import',
            'message' => 'Import failed.',
            'error' => $message,
            'completed_at' => now()->timestamp,
        ]);
    }

    private function cleanupUploadedImport(string $importId): void
    {
        $status = $this->getStatus($importId);
        $filePath = $status['file_path'] ?? '';
        $importsPath = storage_path('app/backups/imports');

        if (
            is_string($filePath)
            && str_starts_with($filePath, $importsPath)
            && is_file($filePath)
        ) {
            @unlink($filePath);
        }
    }

    private function updateStatus(string $importId, array $changes): void
    {
        $current = $this->getStatus($importId) ?? [];
        $this->putStatus($importId, array_merge($current, $changes));
    }

    private function putStatus(string $importId, array $status): void
    {
        Cache::put(self::CACHE_PREFIX.$importId, $status, self::CACHE_TTL_SECONDS);
    }
}
