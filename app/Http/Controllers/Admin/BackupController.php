<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupServerRequirements;
use App\Services\DatabaseImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function __construct(
        private DatabaseImportService $importService,
        private BackupServerRequirements $serverRequirements
    ) {}

    public function index()
    {
        return Inertia::render('Backups/Index');
    }

    public function serverRequirements()
    {
        return response()->json($this->serverRequirements->assess());
    }

    public function deleteFile($fileName)
    {
        if (! $this->isValidSqlFileName($fileName)) {
            return response()->json(['success' => false, 'message' => 'Invalid file name'], 422);
        }

        $filePath = storage_path('app/backups/'.$fileName);
        if (file_exists($filePath)) {
            unlink($filePath);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'File not found'], 404);
    }

    public function downloadBackup($fileName)
    {
        if (! $this->isValidSqlFileName($fileName)) {
            abort(422);
        }

        $filePath = storage_path('app/backups/'.$fileName);
        if (file_exists($filePath)) {
            return response()->download($filePath, $fileName);
        }

        abort(404);
    }

    public function getBackups()
    {
        $backups = [];
        $backupPath = storage_path('app/backups');
        if (file_exists($backupPath) && is_dir($backupPath)) {
            $files = scandir($backupPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== 'imports') {
                    $filePath = $backupPath.'/'.$file;
                    if (! is_file($filePath)) {
                        continue;
                    }

                    $fileSize = filesize($filePath);
                    $fileTime = filemtime($filePath);
                    $backups[] = [
                        'name' => $file,
                        'size' => $this->formatFileSize($fileSize),
                        'time' => $fileTime,
                        'raw_time' => $fileTime,
                        'path' => route('backups.downloadBackup', $file),
                    ];
                }
            }

            usort($backups, function ($a, $b) {
                return $b['raw_time'] - $a['raw_time'];
            });
        }

        return response()->json($backups);
    }

    public function uploadImport(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:1048576',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'sql') {
                        $fail('The file must be a .sql database dump.');
                    }
                },
            ],
        ]);

        $importsPath = storage_path('app/backups/imports');
        if (! file_exists($importsPath)) {
            mkdir($importsPath, 0755, true);
        }

        $importId = (string) Str::uuid();
        $storedName = $importId.'.sql';
        $request->file('file')->move($importsPath, $storedName);
        $filePath = $importsPath.'/'.$storedName;

        $this->importService->createImport($filePath, $request->file('file')->getClientOriginalName());

        return response()->json([
            'success' => true,
            'import_id' => $importId,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'bytes_total' => filesize($filePath) ?: 0,
        ]);
    }

    public function startImport(Request $request)
    {
        $request->validate([
            'import_id' => 'required|uuid',
        ]);

        $importId = $request->input('import_id');
        $status = $this->importService->getStatus($importId);

        if (! $status) {
            return response()->json(['success' => false, 'message' => 'Import session not found.'], 404);
        }

        if (in_array($status['status'] ?? null, ['importing', 'queued', 'completed'], true)) {
            return response()->json([
                'success' => true,
                'import_id' => $importId,
                'status' => $status,
            ]);
        }

        try {
            $this->importService->dispatchBackgroundImport($importId);
        } catch (\RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'import_id' => $importId,
        ]);
    }

    public function importFromBackup($fileName)
    {
        if (! $this->isValidSqlFileName($fileName)) {
            return response()->json(['success' => false, 'message' => 'Invalid file name'], 422);
        }

        $filePath = storage_path('app/backups/'.$fileName);

        if (! is_file($filePath)) {
            return response()->json(['success' => false, 'message' => 'Backup file not found.'], 404);
        }

        $importId = $this->importService->createImport($filePath, $fileName);

        try {
            $this->importService->dispatchBackgroundImport($importId);
        } catch (\RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'import_id' => $importId,
            'file_name' => $fileName,
            'bytes_total' => filesize($filePath) ?: 0,
        ]);
    }

    public function importStatus($importId)
    {
        if (! Str::isUuid($importId)) {
            return response()->json(['success' => false, 'message' => 'Invalid import id.'], 422);
        }

        $status = $this->importService->getStatus($importId);

        if (! $status) {
            return response()->json(['success' => false, 'message' => 'Import session not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $status,
        ]);
    }

    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    private function isValidSqlFileName(string $fileName): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]+\.sql$/', $fileName);
    }

    public function dumpDatabase()
    {
        $connection = config('database.connections.'.config('database.default'));
        $dbHost = $connection['host'] ?? '127.0.0.1';
        $dbPort = (string) ($connection['port'] ?? 3306);
        $dbUser = $connection['username'] ?? '';
        $dbPass = $connection['password'] ?? '';
        $dbName = $connection['database'] ?? '';

        $backupPath = storage_path('app/backups');
        if (! file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $fileName = 'backup_'.date('Y-m-d_H-i-s').'_'.microtime(true).'.sql';
        $filePath = $backupPath.'/'.$fileName;

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);

        if ($resultCode === 0 && is_file($filePath) && filesize($filePath) > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully.',
                'file_name' => $fileName,
            ]);
        }

        if (is_file($filePath)) {
            @unlink($filePath);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error dumping database. Verify mysqldump is installed and database credentials are correct.',
        ], 500);
    }
}
