<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PluginsVersion;
use App\Services\Plugin\PluginLogoUrl;
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
            'file' => 'required|file|mimes:zip',
            'settings' => 'required|json'
        ]);

        $file = $request->file('file');
        $destinationPath = storage_path('/app/private');

        $settings = json_decode($request->settings);

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
            'settings' => $settings,
        ];

        PluginsVersion::create($data);

        return back()->with('success', 'Version created successfully');
    }
    public function updateVersion(Request $request, $id)
    {
        $pluginsVersion = PluginsVersion::findOrFail($id);
        $request->validate([
            'version' => 'required|unique:plugins_versions,version,' . $pluginsVersion->id,
            'settings' => 'required',
            'file' => 'nullable|mimes:zip'
        ]);

        $file = $request->file('file');
        if ($file) {
            $destinationPath = storage_path('/app/private');


            // Create the directory if it does not exist
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $fileName = 'wpsalehub-' . $request->version . '.' . $file->extension();
            $file->move($destinationPath, $fileName);
            $path = 'app/private/' . $fileName;
        }

        $data = [
            'version' => $request->version,
            'download_count' => 0,
            'created_by' => Auth::id(),
            'settings' => $request->settings,
        ];

        if (isset($path) && $path) {
            $data['path'] = $path;
        }

        $pluginsVersion->update($data);

        return back()->with('success', 'Version updated successfully');
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
        return $this->serveBrandAsset('app_icon.jpg');
    }

    public function brandAsset(string $asset)
    {
        return $this->serveBrandAsset($asset);
    }

    private function serveBrandAsset(string $asset)
    {
        $allowed = [
            'icon-128.png',
            'icon-256.png',
            'app_logo.png',
            'app_icon.jpg',
        ];

        if (! in_array($asset, $allowed, true)) {
            abort(404);
        }

        $path = public_path('images/woo-easy-life/' . $asset);

        if ($asset === 'app_icon.jpg' && ! file_exists($path)) {
            $path = public_path('logo.webp');
        }

        if (! file_exists($path)) {
            abort(404);
        }

        $file = file_get_contents($path);
        $type = mime_content_type($path) ?: 'application/octet-stream';

        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function downloadApp()
    {
        $plugins = PluginsVersion::orderBy('created_at', 'desc')->first();
        if (!$plugins) {
            abort(404);
        }
        $plugins->increment('download_count');
        $path = $plugins->path;
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
    public function getMetadata()
    {
        $plugins = PluginsVersion::orderBy('created_at', 'desc')->first();
        if (!$plugins) {
            abort(404);
        }

        try {
            $settings = json_decode($plugins->settings, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $th) {
            $settings = json_decode($plugins->settings, true) ?? [];
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        $settings['icons'] = PluginLogoUrl::icons();

        return $settings;
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


    public function deleteVersion($id)
    {
        $version = PluginsVersion::findOrFail($id);

        $path = storage_path($version->path);
        $version->delete();
        return back()->with('success', 'Version deleted successfully');
    }

    public function forceDeleteVersion($id)
    {
        $version = PluginsVersion::findOrFail($id);

        $path = storage_path($version->path);

        try {
            if (file_exists($path)) {
                unlink($path);
            }
        } catch (\Throwable $th) {
        }
        $version->delete();
        return back()->with('success', 'Version deleted successfully');
    }
}
