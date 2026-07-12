<?php

namespace App\Http\Controllers;

use App\LogHelper;
use App\Models\PluginsVersion;
use App\Services\DownloadGateService;
use App\Services\LandingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDownloadGateController extends Controller
{
    public function __construct(
        private DownloadGateService $downloadGate,
        private LandingSettingsService $landingSettings,
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'website' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->downloadGate->sendOtp(
                (string) $validated['name'],
                (string) $validated['phone'],
                (string) $validated['website'],
                (string) $request->ip(),
            );

            $status = ($result['ok'] ?? false) ? 200 : 429;

            return response()->json($result, $status);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $th) {
            LogHelper::saveLog('download gate send otp error', $th->getMessage());

            return response()->json([
                'message' => 'OTP পাঠানো যায়নি। কিছুক্ষণ পর আবার চেষ্টা করুন।',
            ], 500);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
        ]);

        try {
            $result = $this->downloadGate->verifyOtp(
                (string) $validated['phone'],
                (string) $validated['otp'],
                (string) $request->ip(),
                (string) $request->userAgent(),
            );

            return response()->json($result);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $th) {
            LogHelper::saveLog('download gate verify otp error', $th->getMessage());

            return response()->json([
                'message' => 'OTP যাচাই করা যায়নি। আবার চেষ্টা করুন।',
            ], 500);
        }
    }

    public function download(Request $request, string $asset): Response|StreamedResponse|JsonResponse
    {
        if (! in_array($asset, ['apk', 'plugin'], true)) {
            abort(404);
        }

        $token = (string) $request->query('token', '');

        try {
            $lead = $this->downloadGate->resolveLeadByToken($token);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($asset === 'apk') {
            $url = $this->landingSettings->appDownloadUrl();

            if (! $url) {
                return response()->json(['message' => 'APK ডাউনলোড লিংক এখনো সেট করা হয়নি।'], 404);
            }

            $this->downloadGate->markDownloaded($lead, 'apk');

            return redirect()->away($url);
        }

        $plugin = PluginsVersion::query()->orderByDesc('created_at')->first();

        if (! $plugin || blank($plugin->path)) {
            $override = $this->landingSettings->pluginDownloadUrl();

            if ($override && ! str_contains($override, '/download-plugins')) {
                $this->downloadGate->markDownloaded($lead, 'plugin');

                return redirect()->away($override);
            }

            return response()->json(['message' => 'প্লাগইন ফাইল পাওয়া যায়নি।'], 404);
        }

        $path = storage_path((string) $plugin->path);

        if (! is_file($path)) {
            return response()->json(['message' => 'প্লাগইন ফাইল পাওয়া যায়নি।'], 404);
        }

        $this->downloadGate->markDownloaded($lead, 'plugin');

        $fileName = basename($path);
        $type = mime_content_type($path) ?: 'application/zip';

        return response()->streamDownload(function () use ($path) {
            echo file_get_contents($path);
        }, $fileName, [
            'Content-Type' => $type,
        ]);
    }
}
