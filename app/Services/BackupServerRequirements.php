<?php

namespace App\Services;

class BackupServerRequirements
{
    public function assess(): array
    {
        $checks = $this->buildChecks();

        return [
            'ready' => $this->checksAreReady($checks),
            'checks' => $checks,
            'instructions' => $this->instructions(),
        ];
    }

    public function isReady(): bool
    {
        return $this->checksAreReady($this->buildChecks());
    }

    private function checksAreReady(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['severity'] ?? '') === 'error' && ! ($check['passed'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    private function buildChecks(): array
    {
        $backupPath = storage_path('app/backups');
        $importsPath = storage_path('app/backups/imports');

        return [
            $this->checkCliTool('mysqldump', 'Required to create database backups.'),
            $this->checkCliTool('mysql', 'Required to import SQL dump files.'),
            $this->checkPhpFunction('exec', 'Required to run backup and import commands in the background.'),
            $this->checkPhpFunction('proc_open', 'Required to stream large SQL files into MySQL.'),
            $this->checkWritableDirectory($backupPath, 'Backup storage directory must be writable.'),
            $this->checkWritableDirectory($importsPath, 'Import upload directory must be writable.'),
            $this->checkPhpIniValue('upload_max_filesize', 1024 * 1024 * 1024, 'Set to at least 1G for large SQL uploads.'),
            $this->checkPhpIniValue('post_max_size', 1024 * 1024 * 1024, 'Must be equal to or larger than upload_max_filesize.'),
            $this->checkPhpIniValue('memory_limit', 256 * 1024 * 1024, 'Set to at least 256M for large imports.'),
        ];
    }

    private function checkCliTool(string $binary, string $detail): array
    {
        $path = $this->resolveBinary($binary);

        return [
            'key' => $binary,
            'label' => strtoupper($binary).' CLI',
            'passed' => $path !== null,
            'severity' => 'error',
            'value' => $path ?? 'Not found in PATH',
            'detail' => $detail,
        ];
    }

    private function checkPhpFunction(string $function, string $detail): array
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $passed = function_exists($function) && ! in_array($function, $disabled, true);

        return [
            'key' => $function,
            'label' => $function.'()',
            'passed' => $passed,
            'severity' => 'error',
            'value' => $passed ? 'Enabled' : 'Disabled',
            'detail' => $detail,
        ];
    }

    private function checkWritableDirectory(string $path, string $detail): array
    {
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $passed = is_dir($path) && is_writable($path);

        return [
            'key' => md5($path),
            'label' => 'Writable: '.basename(dirname($path)).'/'.basename($path),
            'passed' => $passed,
            'severity' => 'error',
            'value' => $passed ? $path : 'Not writable',
            'detail' => $detail,
        ];
    }

    private function checkPhpIniValue(string $setting, int $minimumBytes, string $detail): array
    {
        $raw = ini_get($setting);
        $bytes = $this->toBytes($raw);
        $passed = $bytes === -1 || $bytes >= $minimumBytes;

        return [
            'key' => $setting,
            'label' => $setting,
            'passed' => $passed,
            'severity' => $passed ? 'info' : 'warning',
            'value' => $raw ?: 'unknown',
            'detail' => $detail,
        ];
    }

    private function resolveBinary(string $binary): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'where '.escapeshellarg($binary).' 2>NUL';
        } else {
            $command = 'command -v '.escapeshellarg($binary).' 2>/dev/null';
        }

        $path = trim((string) shell_exec($command));

        return $path !== '' ? $path : null;
    }

    private function toBytes(string|false $value): int
    {
        if ($value === false || $value === '') {
            return 0;
        }

        $value = trim(strtolower((string) $value));

        if ($value === '-1') {
            return -1;
        }

        $unit = substr($value, -1);
        $number = (float) $value;

        if ($unit === 'g') {
            return (int) ($number * 1024 * 1024 * 1024);
        }

        if ($unit === 'm') {
            return (int) ($number * 1024 * 1024);
        }

        if ($unit === 'k') {
            return (int) ($number * 1024);
        }

        return (int) $number;
    }

    private function instructions(): array
    {
        return [
            [
                'title' => 'MySQL client tools',
                'items' => [
                    'Install MySQL client utilities so both mysql and mysqldump are available in the web server PATH.',
                    'Ubuntu/Debian: sudo apt install mysql-client',
                    'macOS (Homebrew): brew install mysql-client',
                    'Verify: mysql --version && mysqldump --version',
                ],
            ],
            [
                'title' => 'PHP upload limits (php.ini)',
                'items' => [
                    'upload_max_filesize = 1024M',
                    'post_max_size = 1024M',
                    'memory_limit = 512M',
                    'max_execution_time = 300 (imports run in a background process, but uploads still use the web request)',
                    'Restart PHP-FPM or Apache after changing php.ini.',
                ],
            ],
            [
                'title' => 'Web server body size (Nginx)',
                'items' => [
                    'Add client_max_body_size 1024M; inside your server or location block.',
                    'Reload Nginx: sudo nginx -t && sudo systemctl reload nginx',
                ],
            ],
            [
                'title' => 'Web server body size (Apache)',
                'items' => [
                    'Set LimitRequestBody 1073741824 in your VirtualHost or .htaccess if needed.',
                ],
            ],
            [
                'title' => 'MySQL server settings',
                'items' => [
                    'Ensure max_allowed_packet is large enough for big INSERT statements (recommended: 256M or higher).',
                    'Example (my.cnf): max_allowed_packet=256M',
                    'The DB user in .env must have CREATE, DROP, INSERT, UPDATE, DELETE, and ALTER privileges for imports.',
                ],
            ],
            [
                'title' => 'Background import process',
                'items' => [
                    'Imports run via php artisan database:import in a detached background process.',
                    'exec() and proc_open() must not be listed in disable_functions.',
                    'On shared hosting, confirm shell_exec/exec are allowed or run imports from a VPS/dedicated server.',
                    'Use a persistent cache driver (file or redis). The array driver will not keep import progress across requests.',
                ],
            ],
            [
                'title' => 'Disk space',
                'items' => [
                    'Keep enough free space under storage/app/backups for uploaded imports and generated dumps.',
                    'Rule of thumb: free space should be at least 3x the size of the largest SQL file you plan to import.',
                ],
            ],
            [
                'title' => 'Recommended workflow',
                'items' => [
                    'Create a fresh backup before importing.',
                    'Prefer importing existing server backups for very large files to skip the upload step.',
                    'Do not close the import dialog until the process completes or reports an error.',
                ],
            ],
        ];
    }
}
