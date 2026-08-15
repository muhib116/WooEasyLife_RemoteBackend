<?php

namespace App\Http\Controllers;

use App\LogHelper;
use App\Services\PublicFraudCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PublicFraudCheckController extends Controller
{
    public function __construct(
        private PublicFraudCheckService $publicFraudCheckService,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        return response()->json($this->publicFraudCheckService->meta($request->ip(), $locale));
    }

    public function check(Request $request): JsonResponse
    {
        set_time_limit(120);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'locale' => ['nullable', 'string', 'in:bn,en'],
        ]);

        $locale = $this->resolveLocale($request, $validated['locale'] ?? null);

        try {
            $result = $this->publicFraudCheckService->check(
                (string) $request->ip(),
                (string) $validated['phone'],
                $locale,
                $request,
            );

            if ($result['limited'] ?? false) {
                return response()->json($result, 429);
            }

            return response()->json($result);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Public fraud check error', $th->getMessage());

            return response()->json([
                'message' => $locale === 'en'
                    ? 'Fraud check could not be completed. Please try again shortly.'
                    : 'ফ্রড চেক সম্পন্ন করা যায়নি। কিছুক্ষণ পর আবার চেষ্টা করুন।',
            ], 500);
        }
    }

    private function resolveLocale(Request $request, ?string $explicit = null): string
    {
        $candidate = $explicit
            ?? $request->input('locale')
            ?? $request->header('X-Locale')
            ?? 'bn';

        return $candidate === 'en' ? 'en' : 'bn';
    }
}
