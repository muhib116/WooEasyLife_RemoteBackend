<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LogHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class LogController extends Controller
{

    public function index()
    {
        return Inertia::render('Logs/Index');
    }

    /**
     * Get list of log files in the "logs/hub-log" directory.
     */
    public function listLogs()
    {
        $logDirectory = storage_path('logs/hub-log');

        if (!File::exists($logDirectory)) {
            return response()->json(['files' => []]);
        }

        $files = collect(File::files($logDirectory))
            ->sortByDesc(fn($file) => $file->getCTime()) // Sort by creation time (latest first)
            ->map(fn($file) => [
                'name' => $file->getFilename(),
                'path' => $file->getRealPath(),
            ])
            ->values();

        return response()->json(['files' => $files]);
    }

    /**
     * Get the content of a log file.
     */
    public function viewLog(Request $request)
    {
        $filePath = $request->get('file');

        if (!$filePath || !File::exists($filePath)) {
            return response()->json(['logs' => []], 404);
        }

        $logs = LogHelper::getLogs($filePath);
        return response()->json(['logs' => $logs]);
    }
}
