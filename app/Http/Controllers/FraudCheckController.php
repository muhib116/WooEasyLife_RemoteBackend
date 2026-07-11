<?php

namespace App\Http\Controllers;

use App\LogHelper;
use App\Services\FraudCheckService;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use App\Services\OrderIntelligence\FraudCheckRuntimeConfig;
use Enan\PathaoCourier\Facades\PathaoCourier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use InvalidArgumentException;

class FraudCheckController extends Controller
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private FraudCheckCoordinator $fraudCheckCoordinator,
        private FraudCheckRuntimeConfig $fraudCheckRuntimeConfig,
    ) {}

    public function index()
    {
        return Inertia::render('FraudCheck/Index', [
            'debugMode' => config('app.debug'),
            'runtimeConfig' => $this->fraudCheckRuntimeConfig->snapshot(),
        ]);
    }

    public function runtimeConfig(): JsonResponse
    {
        return response()->json($this->fraudCheckRuntimeConfig->snapshot());
    }

    public function updateRuntimeConfig(Request $request): JsonResponse
    {
        $allowed = array_keys(FraudCheckRuntimeConfig::FIELDS);
        $payload = $request->only($allowed);

        return response()->json([
            'message' => 'Fraud check configuration saved.',
            'config' => $this->fraudCheckRuntimeConfig->update($payload),
        ]);
    }

    public function resetRuntimeConfig(): JsonResponse
    {
        return response()->json([
            'message' => 'Fraud check configuration reset to .env defaults.',
            'config' => $this->fraudCheckRuntimeConfig->resetToEnv(),
        ]);
    }

    public function saveSteadfastCurl(Request $request)
    {
        if ($request->curl_text) {
            $curl = $this->normalizeSteadfastCurl((string) $request->curl_text);
            file_put_contents(__DIR__ . '/curlcode.txt', $curl);
        }

        return back()->with('success', 'Steadfast CURL code is saved successfully!');
    }

    private function normalizeSteadfastCurl(string $curl): string
    {
        $curl = preg_replace(
            '#https://(?:www\.)?steadfast\.com\.bd/user/(?:consignment/getbyphone|frauds/check)/\d+#',
            'https://www.steadfast.com.bd/user/frauds/check/01770989591',
            $curl
        );

        if (!preg_match('/\s\-s\b/', $curl)) {
            $curl = preg_replace('/^curl\s/', 'curl -s ', $curl);
        }

        return $curl;
    }

    public function expire()
    {
        $time_left = null;
        $steadfast_curl = file_exists(__DIR__ . '/curlcode.txt')
            ? file_get_contents(__DIR__ . '/curlcode.txt')
            : '';
        $credentialStatus = $this->fraudCheckService->credentialStatus();

        return Inertia::render('FraudCheck/Expire', compact('time_left', 'steadfast_curl', 'credentialStatus'));
    }

    public function getExpire()
    {
        return PathaoCourier::GET_ACCESS_TOKEN_EXPIRY_DAYS_LEFT();
    }

    public function expireSession()
    {
        abort_unless(config('app.debug'), 404);

        return response()->json($this->fraudCheckService->expireSessions());
    }

    public function renewExpire()
    {
        $headers = [
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ];

        $data = [
            'client_id' => config('pathao-courier.pathao_client_id'),
            'client_secret' => config('pathao-courier.pathao_client_secret'),
            'grant_type' => config('pathao-courier.pathao_grant_type_password'),
            'username' => config('fraud-checker-bd-courier.pathao.user'),
            'password' => config('fraud-checker-bd-courier.pathao.password'),
        ];

        $pathaoResponse = Http::withHeaders($headers)->post(
            'https://api-hermes.pathao.com/aladdin/api/v1/issue-token',
            $data
        );

        $body = $pathaoResponse->json();
        $token = Arr::get($body, 'access_token');
        $refreshToken = Arr::get($body, 'refresh_token');
        $expiresIn = time() + (int) Arr::get($body, 'expires_in', 0);

        $newToken = [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'updated_at' => now(),
        ];

        $isUpdated = false;

        if ($token && $refreshToken && config('pathao-courier.pathao_secret_token')) {
            DB::table(config('pathao-courier.pathao_db_table_name'))
                ->where('secret_token', '=', config('pathao-courier.pathao_secret_token'))
                ->update($newToken);
            $isUpdated = true;
        }

        return [
            'message' => $token ? 'Token has been renewed successfully!' : 'Token renewal failed.',
            'token' => $newToken,
            'db_update_status' => $isUpdated,
            'pathao_response' => $body,
        ];
    }

    public function check(Request $request)
    {
        set_time_limit(120);

        try {
            if (is_array($request->data)) {
                return $this->successResponse(
                    $this->fraudCheckCoordinator->checkMultiple($request, $request->data),
                );
            }

            $response = $this->fraudCheckCoordinator->checkSingle($request, $request->all());

            return response()->json($response);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Fraud check error', $th->getMessage());

            return response()->json([
                'message' => 'Unable to complete fraud check.',
            ], 500);
        }
    }

    protected function sendEvent(string $event, string $data): void
    {
        echo "event: {$event}\n";
        echo "data: {$data}\n\n";
    }

    public function checkStream(Request $request)
    {
        set_time_limit(300);

        return response()->stream(function () use ($request) {
            $total = count($request->data ?? []);
            $processed = 0;

            foreach ($request->data ?? [] as $number) {
                $processed++;

                try {
                    $report = $this->fraudCheckCoordinator->checkSingle($request, $number);
                } catch (\Throwable $th) {
                    LogHelper::saveLog('Fraud stream check error', $th->getMessage());
                    $report = [
                        'total_order' => 0,
                        'confirmed' => 0,
                        'frauds' => [],
                        'cancel' => 0,
                        'success_rate' => 'No order history found!',
                        'courier' => [],
                        'error' => $th->getMessage(),
                    ];
                }

                $progress = $total > 0 ? ($processed / $total) * 100 : 100;
                $this->sendEvent('user_report', json_encode([
                    'data' => [
                        'id' => $number['id'],
                        'phone' => $number['phone'],
                        'report' => $report,
                    ],
                    'progress' => [
                        'processed' => $processed,
                        'total' => $total,
                        'percentage' => $progress,
                    ],
                ]));

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                usleep(100000);
            }

            $this->sendEvent('done', json_encode(['message' => 'All processing complete']));
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
