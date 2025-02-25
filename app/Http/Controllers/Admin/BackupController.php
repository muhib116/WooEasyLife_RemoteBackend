<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function index()
    {
        return Inertia::render('Backups/Index');
    }

    public function deleteFile($fileName)
    {
        $filePath = storage_path('app/backups/' . $fileName);
        if (file_exists($filePath)) {
            unlink($filePath);
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }
    }

    public function downloadBackup($fileName)
    {
        $filePath = storage_path('app/backups/' . $fileName);
        if (file_exists($filePath)) {
            return response()->download($filePath, $fileName);
        } else {
            abort(404);
        }
    }

    public function getBackups()
    {
        $backups = [];
        $backupPath = storage_path('app/backups');
        if (file_exists($backupPath) && is_dir($backupPath)) {
            $files = scandir($backupPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $backupPath . '/' . $file;
                    $fileSize = filesize($filePath);
                    $fileTime = filemtime($filePath);
                    $backups[] = [
                        'name' => $file,
                        'size' => $this->formatFileSize($fileSize),
                        'time' => $fileTime, // Convert to milliseconds for JavaScript
                        'raw_time' => $fileTime, // Keep the raw time for sorting
                        'path' => route('backups.downloadBackup', $file),
                    ];
                }
            }
            // Sort backups array by time in descending order
            usort($backups, function ($a, $b) {
                return $b['raw_time'] - $a['raw_time'];
            });
        }
        return response()->json($backups);
    }

    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function dumpDatabase()
    {
        $dbHost = env('DB_HOST');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        $dbName = env('DB_DATABASE');

        $backupPath = storage_path('app/backups');
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true); // Ensure the backups directory exists
        }

        $fileName = 'backup_' . date('Y-m-d_H-i-s') . '_' . microtime(true) . '.sql';
        $filePath = $backupPath . '/' . $fileName;

        // Build mysqldump command
        $command = "mysqldump --host=$dbHost --user=$dbUser --password=$dbPass $dbName > $filePath";

        // Execute the command
        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);

        // Check if the dump was successful
        if ($resultCode === 0) {
            return "Database dump successful! File: $filePath";
        } else {
            return "Error dumping database. Please check your database credentials and mysqldump installation.";
        }
    }
}
