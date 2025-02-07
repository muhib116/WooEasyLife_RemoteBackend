<?php

namespace App;

use App\Models\AccessToken;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogHelper
{
    /**
     * Save a structured log message to a chunked log file.
     *
     * @param string $title The title of the log message.
     * @param string $message The detailed log message.
     * @param string $baseFileName The base name for the log file (e.g., 'errors.log').
     */
    public static function saveLog($title, $message, $baseFileName = 'errors.log')
    {
        try {
            $logDirectory = storage_path('logs/hub-log');

            // Ensure log directory exists
            File::ensureDirectoryExists($logDirectory, 0755, true);

            // Determine the latest chunk file
            $chunkedFile = self::getChunkedFileName($logDirectory, $baseFileName);

            $endpoint = '';
            $frontendDomain = '';
            $token = '';
            $lineInfo = null;

            try {
                $endpoint = request()->url();
                $frontendDomain = request()->headers->get('origin') ?? request()->headers->get('referer');
                $token = request()->bearerToken();
            } catch (\Throwable $th) {
            }
            try {
                $token = request()->bearerToken();
                $accessToken = AccessToken::findToken($token);
                $user = $accessToken->tokenable;
                if ($user) {
                    $token = 'ID: (' . $accessToken->tokenable_id . ') User: (' . $user->id . ') ' . $token;
                }
            } catch (\Throwable $th) {
            }

            try {
                $backtrace = debug_backtrace();
                $lineInfo = [
                    'line' => $backtrace[1]['line'],
                    'function' => $backtrace[1]['function'],
                    'class' => $backtrace[1]['class'],
                ];
            } catch (\Throwable $th) {
                //throw $th;
            }

            // Create a structured log entry
            $logEntry = json_encode([
                'timestamp' => now(),
                'title' => $title,
                'message' => $message,
                'endpoint' => $endpoint,
                'frontendDomain' => $frontendDomain,
                'token' => $token,
                'lineInfo' => $lineInfo
            ]) . PHP_EOL;

            // Save to the latest chunked log file
            File::append($chunkedFile, $logEntry);
        } catch (\Throwable $th) {
            Log::error('Failed to write to custom log: ' . $th->getMessage());
        }
    }

    /**
     * Get the appropriate chunked log file name in "logs/hub-log".
     *
     * @param string $directory The directory where logs are stored.
     * @param string $baseFileName The base log file name.
     * @return string Full path to the appropriate chunked log file.
     */
    private static function getChunkedFileName(string $directory, string $baseFileName)
    {
        $chunk = 1;
        $filePath = "{$directory}/{$chunk}-{$baseFileName}";

        // Loop to find the correct chunked file
        while (File::exists($filePath) && File::size($filePath) >= 1048576) { // 1MB = 1048576 bytes
            $chunk++;
            $filePath = "{$directory}/{$chunk}-{$baseFileName}";
        }

        return $filePath;
    }

    /**
     * Get and parse logs from a given log file.
     *
     * @param string $filePath The log file path.
     * @return array Parsed log entries as an array of objects.
     */
    public static function getLogs(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        $logs = [];
        $lines = File::lines($filePath);

        foreach ($lines as $line) {
            $decodedLog = json_decode($line, true);
            if ($decodedLog) {
                $logs[] = $decodedLog;
            }
        }

        return $logs;
    }

    /**
     * Get the list of log files sorted by latest.
     *
     * @return array List of log files with name and path.
     */
    public static function getLogFiles(): array
    {
        $logDirectory = storage_path('logs/hub-log');

        if (!File::exists($logDirectory)) {
            return [];
        }

        return collect(File::files($logDirectory))
            ->sortByDesc(fn($file) => $file->getCTime()) // Sort latest first
            ->map(fn($file) => [
                'name' => $file->getFilename(),
                'path' => $file->getRealPath(),
            ])
            ->values()
            ->toArray();
    }
}
