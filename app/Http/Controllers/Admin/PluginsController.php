<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PluginsVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class PluginsController extends Controller
{

    public function index()
    {
        $plugins_link = storage_path('/private/plugins.zip');
        $versions = PluginsVersion::query()->orderBy('id', 'desc')->get() ?? [];
        return Inertia::render('Plugins/Index', compact('plugins_link', 'versions'));
    }

    public function createVersion(Request $request)
    {
        $request->validate([
            'version' => 'required|unique:plugins_versions,version',
            'file' => 'required|file|mimes:zip'
        ]);

        $file = $request->file('file');
        $destinationPath = storage_path('/app/private');

        // Create the directory if it does not exist
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }


        $fileName = 'wpsalehub-' . $request->version . '.' . $file->extension();
        $file->move($destinationPath, $fileName);
        $path = 'app/private/' . $fileName;

        $data = [
            'version' => $request->version,
            'path' => $path,
            'download_count' => 0,
            'created_by' => Auth::id(),
            'settings' => $request->settings,
        ];

        PluginsVersion::create($data);

        return back()->with('success', 'Version created successfully');
    }

    public function downloadVersion($version)
    {
        $plugins = PluginsVersion::where('version', $version)->first();
        $path = storage_path($plugins->path);

        if (!file_exists($path)) {
            abort(404);
        }
        $file = file_get_contents($path);
        $type = mime_content_type($path);
        $fileName = basename($path);
        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function appLogo()
    {
        $path = public_path('logo.webp');

        if (!file_exists($path)) {
            abort(404);
        }

        $file = file_get_contents($path);
        $type = mime_content_type($path);
        $fileName = basename($path);
        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function downloadApp()
    {
        $path = storage_path('/app/private/plugins.zip');

        if (!file_exists($path)) {
            abort(404);
        }

        $file = file_get_contents($path);
        $type = mime_content_type($path);

        $fileName = basename($path);
        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
    public function pluginsMetadata()
    {
        $path = public_path('/app/private/plugins.zip');

        if (!file_exists($path)) {
            abort(404);
        }

        $file = file_get_contents($path);
        $type = mime_content_type($path);

        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
        ]);
    }
}
