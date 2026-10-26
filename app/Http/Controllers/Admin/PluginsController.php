<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PluginsVersion;
use App\Services\Plugin\PluginLogoUrl;
use App\Services\Plugin\PluginReleaseGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
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
        @ini_set('max_execution_time', '300');

        $validator = Validator::make(
            $request->all(),
            $this->pluginVersionValidationRules(fileRequired: true),
            $this->pluginVersionValidationMessages()
        );

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->storeNewPluginVersion(
            $request->version,
            $request->file('file'),
            PluginReleaseGate::holdUnlessSpecified($this->normalizePluginSettings($request->settings)),
            Auth::id()
        );

        return redirect()->route('plugins.index')->with('success', 'Version created successfully');
    }

    public function createVersionApi(Request $request)
    {
        @ini_set('max_execution_time', '300');

        $validator = Validator::make(
            $request->all(),
            $this->pluginVersionValidationRules(fileRequired: true),
            $this->pluginVersionValidationMessages()
        );

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $version = $this->storeNewPluginVersion(
                $request->version,
                $request->file('file'),
                PluginReleaseGate::holdUnlessSpecified($this->normalizePluginSettings($request->settings)),
                null
            );
        } catch (\Throwable $th) {
            return $this->errorResponse('Unable to store plugin version', 500);
        }

        return $this->successResponse(
            $version->fresh(),
            'Version created successfully',
            201
        );
    }

    public function updateVersion(Request $request, $id)
    {
        @ini_set('max_execution_time', '300');

        $pluginsVersion = PluginsVersion::findOrFail($id);
        $request->validate(
            $this->pluginVersionValidationRules($pluginsVersion->id),
            $this->pluginVersionValidationMessages()
        );

        $data = [
            'version' => $request->version,
            'settings' => $this->normalizePluginSettings($request->settings),
        ];

        $file = $request->file('file');
        if ($file) {
            $data['path'] = $this->storePluginZip($request->version, $file);
            $this->deletePluginZipIfOrphaned($pluginsVersion->path, $data['path']);
            $absolute = storage_path($data['path']);
            if (is_file($absolute)) {
                $data['settings']['sha256'] = hash_file('sha256', $absolute);
            }
        }

        // In-place ZIP replace keeps the same path; version/settings may be unchanged too.
        // Eloquent then skips the UPDATE and leaves updated_at stale — force a touch.
        $pluginsVersion->fill($data);
        $pluginsVersion->updated_at = now();
        $pluginsVersion->save();

        return redirect()->route('plugins.index')->with('success', 'Version updated successfully');
    }

    public function downloadVersion($version)
    {
        $plugins = PluginsVersion::where('version', $version)->first();
        $path = storage_path($plugins->path);

        if (! file_exists($path)) {
            abort(404);
        }
        $file = file_get_contents($path);
        $type = mime_content_type($path);
        $fileName = basename($path);

        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
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

        $path = public_path('images/woo-easy-life/'.$asset);

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

    public function downloadApp(Request $request)
    {
        $plugins = $this->eligiblePluginVersion($request);
        if (! $plugins) {
            abort(404);
        }

        $path = storage_path($plugins->path);
        if (! is_file($path) || ! is_readable($path)) {
            abort(404);
        }

        $fileName = basename($path);
        // Plugin packages are always zips. mime_content_type() can mis-detect
        // edge files as text/plain and break WP download_url / upgraders.
        $type = 'application/zip';
        $detected = @mime_content_type($path);
        if (is_string($detected) && strpos(strtolower($detected), 'zip') !== false) {
            $type = $detected;
        }
        $size = filesize($path);

        // Stream the zip — loading multi‑MB packages into memory stalls WP updaters
        // and triggers retries that burn the shared IP rate limit.
        $plugins->increment('download_count');

        $headers = [
            'Content-Type' => $type,
            'Cache-Control' => 'no-cache, private',
        ];
        if ($size !== false && $size >= 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->streamDownload(function () use ($path) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            try {
                while (! feof($handle)) {
                    $chunk = fread($handle, 1024 * 256);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                    if (function_exists('fastcgi_finish_request') === false) {
                        flush();
                    }
                }
            } finally {
                fclose($handle);
            }
        }, $fileName, $headers);
    }

    public function getMetadata(Request $request)
    {
        $plugins = $this->eligiblePluginVersion($request);
        if (! $plugins) {
            abort(404);
        }

        $settings = $this->normalizePluginSettings($plugins->settings);
        $settings['icons'] = PluginLogoUrl::resolve($settings['icons'] ?? null);
        $settings['version'] = $settings['version'] ?? $plugins->version;

        return $settings;
    }

    private function eligiblePluginVersion(Request $request): ?PluginsVersion
    {
        $caller = PluginReleaseGate::caller(
            $request->header('Authorization'),
            $request->header('origin'),
            $request->query()
        );
        $rows = [];
        foreach (PluginsVersion::query()->get() as $row) {
            $rows[] = [
                'id' => $row->id,
                'version' => (string) $row->version,
                'created_at' => $row->created_at ? $row->created_at->getTimestamp() : 0,
                'settings' => $this->normalizePluginSettings($row->settings),
                'path' => (string) $row->path,
            ];
        }

        $chosen = PluginReleaseGate::select($rows, $caller);
        if ($chosen === null) {
            Log::info('plugin.release', PluginReleaseGate::audit(['version' => '', 'settings' => []], $caller, false));

            return null;
        }

        Log::info('plugin.release', PluginReleaseGate::audit($chosen, $caller, true));

        return PluginsVersion::query()->find($chosen['id'] ?? 0);
    }

    /**
     * @param  mixed  $settings
     * @return array<string, mixed>
     */
    private function normalizePluginSettings($settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (is_object($settings)) {
            $decoded = json_decode(json_encode($settings), true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_string($settings) && $settings !== '') {
            $decoded = json_decode($settings, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginVersionValidationRules(?int $ignoreId = null, bool $fileRequired = false): array
    {
        return [
            'version' => ['required', 'string', 'max:50', $this->uniquePluginVersionRule($ignoreId)],
            'settings' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_array($value)) {
                        return;
                    }

                    if (! is_string($value) || trim($value) === '') {
                        $fail('The settings field must be valid JSON.');

                        return;
                    }

                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('The settings field must be valid JSON.');
                    }
                },
            ],
            'file' => $fileRequired
                ? ['required', 'file', 'extensions:zip', 'max:102400']
                : ['nullable', 'file', 'extensions:zip', 'max:102400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pluginVersionValidationMessages(): array
    {
        return [
            'file.extensions' => 'The plugin file must be a ZIP archive.',
            'file.max' => 'The plugin ZIP may not be greater than 100MB.',
            'file.uploaded' => 'The plugin ZIP failed to upload. It may exceed the server upload_max_filesize or post_max_size limit.',
            'settings.json' => 'The settings field must be valid JSON.',
        ];
    }

    private function uniquePluginVersionRule(?int $ignoreId = null): Unique
    {
        $rule = Rule::unique('plugins_versions', 'version')->whereNull('deleted_at');

        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    private function storePluginZip(string $version, UploadedFile $file): string
    {
        $destinationPath = storage_path('app/private');

        if (! file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'zip');
        $fileName = 'wpsalehub-'.$version.'.'.$extension;
        $file->move($destinationPath, $fileName);

        return 'app/private/'.$fileName;
    }

    private function deletePluginZipIfOrphaned(?string $oldPath, string $newPath): void
    {
        if (! $oldPath || $oldPath === $newPath) {
            return;
        }

        $oldAbsolute = storage_path($oldPath);
        $newAbsolute = storage_path($newPath);

        if (! is_file($oldAbsolute)) {
            return;
        }

        $oldReal = realpath($oldAbsolute);
        $newReal = realpath($newAbsolute);

        if ($oldReal && $newReal && $oldReal === $newReal) {
            return;
        }

        @unlink($oldAbsolute);
    }

    private function storeNewPluginVersion(
        string $version,
        UploadedFile $file,
        array $settings,
        ?int $createdBy
    ): PluginsVersion {
        $path = $this->storePluginZip($version, $file);
        $absolute = storage_path($path);
        if (is_file($absolute)) {
            $settings['sha256'] = hash_file('sha256', $absolute);
        }

        return PluginsVersion::create([
            'version' => $version,
            'path' => $path,
            'download_count' => 0,
            'created_by' => $createdBy,
            'settings' => $settings,
        ]);
    }

    public function pluginsMetadata()
    {
        $path = public_path('/app/private/plugins.zip');

        if (! file_exists($path)) {
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
