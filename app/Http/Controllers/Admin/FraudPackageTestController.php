<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FraudCheck\CourierPackageRepairService;
use App\Services\FraudCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

class FraudPackageTestController extends Controller
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private CourierPackageRepairService $packageRepairService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('FraudCheck/PackageTest', [
            'package' => [
                'name' => 'internal fraud checkers',
                'version' => 'app',
                'couriers' => ['steadfast', 'pathao', 'redx', 'paperfly', 'carrybee'],
            ],
            'credentials' => [
                'pathao' => filled(config('courier-checker.pathao.user'))
                    || filled(config('fraud-checker-bd-courier.pathao.user')),
                'steadfast' => filled(config('courier-checker.steadfast.user'))
                    || filled(config('fraud-checker-bd-courier.steadfast.user')),
                'redx' => filled(config('courier-checker.redx.phone')),
                'paperfly' => filled(config('courier-checker.paperfly.user'))
                    || filled(config('fraud-checker-bd-courier.paperfly.user')),
                'carrybee' => filled(config('courier-checker.carrybee.phone')),
            ],
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        set_time_limit(180);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['in:package,internal'],
        ]);

        $sources = $validated['sources'] ?? ['package', 'internal'];

        try {
            $phone = $this->fraudCheckService->normalizePhone($validated['phone']);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $result = [
            'phone' => $phone,
            'checked_at' => now()->toIso8601String(),
            'package' => null,
            'internal' => null,
        ];

        if (in_array('package', $sources, true)) {
            $started = microtime(true);

            try {
                $repaired = $this->packageRepairService->checkAndRepair($phone);
                $result['package'] = [
                    'ok' => true,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'data' => $repaired['repaired'],
                    'raw' => $repaired['raw'],
                    'repairs' => $repaired['repairs'],
                    'analysis' => $repaired['analysis'],
                ];
            } catch (Throwable $e) {
                $result['package'] = [
                    'ok' => false,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (in_array('internal', $sources, true)) {
            $started = microtime(true);

            try {
                $data = $this->fraudCheckService->getReport($phone);
                $result['internal'] = [
                    'ok' => true,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'data' => $data,
                ];
            } catch (Throwable $e) {
                $result['internal'] = [
                    'ok' => false,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json($result);
    }
}
