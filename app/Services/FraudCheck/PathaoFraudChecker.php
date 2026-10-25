<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Enan\PathaoCourier\Facades\PathaoCourier;
use Enan\PathaoCourier\Requests\PathaoUserSuccessRateRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PathaoFraudChecker
{
    private const MERCHANT_TOKEN_MINUTES = 50;

    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
    ) {}

    public function check(string $phone): array
    {
        $ratingFallback = null;

        if ($this->hasMerchantCredentials()) {
            $report = $this->checkViaMerchantPortal($phone);

            if (CourierReportFormatter::hasDeliveryCounts($report)) {
                return $report;
            }

            if (CourierReportFormatter::isRatingOnly($report)) {
                $ratingFallback = $report;
            }
        }

        if ($this->hasHermesTokenInDatabase()) {
            $report = $this->checkViaHermes($phone);

            if (CourierReportFormatter::hasDeliveryCounts($report)) {
                return $report;
            }

            if ($ratingFallback === null && CourierReportFormatter::isRatingOnly($report)) {
                $ratingFallback = $report;
            }
        }

        if ($this->hasHermesIssueTokenCredentials()) {
            $report = $this->checkViaHermesIssueToken($phone);

            if (CourierReportFormatter::hasDeliveryCounts($report)) {
                return $report;
            }

            if ($ratingFallback === null && CourierReportFormatter::isRatingOnly($report)) {
                $ratingFallback = $report;
            }
        }

        if ($ratingFallback !== null) {
            return $ratingFallback;
        }

        LogHelper::saveLog('Pathao fraud check skipped', 'Pathao credentials are not configured or returned no data.');

        return CourierReportFormatter::emptyReport();
    }

    /**
     * Pool the merchant success call when a Pathao token is already cached.
     * Login and Hermes fallbacks stay on check().
     *
     * @return array{send: callable, read: callable}|null
     */
    public function poolPlan(string $phone, ?array $credentials = null): ?array
    {
        foreach ($this->credentials->candidates('pathao') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('pathao', $candidate['identifier']);
            $token = Cache::get($cacheKey);

            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            return [
                'send' => function (Pool $pool, string $name) use ($phone, $token): void {
                    $pool->as($name)
                        ->timeout(30)
                        ->acceptJson()
                        ->withToken($token)
                        ->withOptions([
                            'curl' => [
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            ],
                        ])
                        ->post('https://merchant.pathao.com/api/v1/user/success', [
                            'phone' => $phone,
                        ]);
                },
                'read' => function (Response $response) use ($candidate): ?array {
                    $result = $this->interpretMerchantHttp($response);

                    if (! $result['ok'] || ! CourierReportFormatter::hasDeliveryCounts($result['report'])) {
                        return null;
                    }

                    $this->credentials->markSuccess($candidate['id']);

                    return $result['report'];
                },
            ];
        }

        return null;
    }

    public function expireSession(): bool
    {
        $cleared = false;
        $secretToken = config('pathao-courier.pathao_secret_token');

        if (filled($secretToken)) {
            $updated = DB::table(config('pathao-courier.pathao_db_table_name'))
                ->where('secret_token', $secretToken)
                ->whereNotNull('token')
                ->update([
                    'token' => null,
                    'refresh_token' => null,
                    'expires_in' => null,
                    'updated_at' => now(),
                ]);

            $cleared = $updated > 0;
        }

        if ($this->credentials->forgetSessionCaches('pathao') > 0) {
            $cleared = true;
        }

        return $cleared;
    }

    private function hasHermesTokenInDatabase(): bool
    {
        if (! filled(config('pathao-courier.pathao_secret_token'))) {
            return false;
        }

        return DB::table(config('pathao-courier.pathao_db_table_name'))
            ->where('secret_token', config('pathao-courier.pathao_secret_token'))
            ->whereNotNull('token')
            ->exists();
    }

    private function hasHermesIssueTokenCredentials(): bool
    {
        return filled(config('pathao-courier.pathao_client_id'))
            && filled(config('pathao-courier.pathao_client_secret'))
            && $this->hasMerchantCredentials();
    }

    private function hasMerchantCredentials(): bool
    {
        return $this->credentials->isConfigured('pathao');
    }

    private function checkViaHermes(string $phone): array
    {
        try {
            $request = new PathaoUserSuccessRateRequest();
            $request->merge(['phone' => $phone]);

            return $this->mapPathaoPayload(PathaoCourier::GET_USER_SUCCESS_RATE($request));
        } catch (\Throwable $th) {
            LogHelper::saveLog('Pathao fraud check error', $th->getMessage());

            return CourierReportFormatter::emptyReport();
        }
    }

    private function checkViaHermesIssueToken(string $phone): array
    {
        try {
            foreach ($this->credentials->loginCandidates('pathao') as $candidate) {
                $tokenResponse = Http::timeout(30)
                    ->acceptJson()
                    ->post('https://api-hermes.pathao.com/aladdin/api/v1/issue-token', [
                        'client_id' => config('pathao-courier.pathao_client_id'),
                        'client_secret' => config('pathao-courier.pathao_client_secret'),
                        'grant_type' => config('pathao-courier.pathao_grant_type_password'),
                        'username' => $candidate['identifier'],
                        'password' => $candidate['password'],
                    ]);

                $tokenBody = $tokenResponse->json();
                $accessToken = Arr::get($tokenBody, 'access_token');

                if (! $tokenResponse->successful() || empty($accessToken)) {
                    $this->credentials->markFailure($candidate['id'], 'Hermes issue-token failed');
                    LogHelper::saveLog('Pathao fraud check error', json_encode($tokenBody));

                    continue;
                }

                $this->persistHermesToken($tokenBody);
                $this->credentials->markSuccess($candidate['id']);

                $successResponse = Http::timeout(30)
                    ->acceptJson()
                    ->withToken($accessToken)
                    ->post('https://api-hermes.pathao.com/api/v1/user/success', [
                        'phone' => $phone,
                    ]);

                if (! $successResponse->successful()) {
                    $this->credentials->markFailure(
                        $candidate['id'],
                        'Hermes success API failed HTTP '.$successResponse->status(),
                    );
                    LogHelper::saveLog('Pathao fraud check error', 'Hermes success API failed with status '.$successResponse->status());

                    continue;
                }

                return $this->mapPathaoPayload([
                    'data' => $successResponse->json(),
                ]);
            }

            return CourierReportFormatter::emptyReport();
        } catch (\Throwable $th) {
            LogHelper::saveLog('Pathao fraud check error', $th->getMessage());

            return CourierReportFormatter::emptyReport();
        }
    }

    private function checkViaMerchantPortal(string $phone): array
    {
        try {
            $skipFreshLogin = [];
            $pathaoBusy = false;

            foreach ($this->credentials->candidates('pathao') as $candidate) {
                $cacheKey = $this->credentials->sessionCacheKey('pathao', $candidate['identifier']);
                $token = Cache::get($cacheKey);

                if (! is_string($token) || trim($token) === '') {
                    continue;
                }

                $result = $this->readMerchantSuccess($phone, $token);

                if ($result['unauthorized']) {
                    Cache::forget($cacheKey);

                    continue;
                }

                if ($result['ok']) {
                    $this->credentials->markSuccess($candidate['id']);

                    return $result['report'];
                }

                $this->credentials->markFailure($candidate['id'], 'Merchant success API failed');
                $skipFreshLogin[$candidate['identifier']] = true;
                $pathaoBusy = $pathaoBusy || $result['busy'];
            }

            if ($pathaoBusy) {
                return CourierReportFormatter::emptyReport();
            }

            foreach ($this->credentials->loginCandidates('pathao') as $candidate) {
                if (isset($skipFreshLogin[$candidate['identifier']])) {
                    continue;
                }

                $token = $this->loginMerchant($candidate);

                if ($token === null) {
                    continue;
                }

                $result = $this->readMerchantSuccess($phone, $token);

                if ($result['unauthorized']) {
                    Cache::forget($this->credentials->sessionCacheKey('pathao', $candidate['identifier']));
                    $this->credentials->markFailure($candidate['id'], 'Merchant token rejected');

                    continue;
                }

                if ($result['ok']) {
                    $this->credentials->markSuccess($candidate['id']);

                    return $result['report'];
                }

                $this->credentials->markFailure($candidate['id'], 'Merchant success API failed');

                if ($result['busy']) {
                    return CourierReportFormatter::emptyReport();
                }
            }

            return CourierReportFormatter::emptyReport();
        } catch (\Throwable $th) {
            LogHelper::saveLog('Pathao fraud check error', $th->getMessage());

            return CourierReportFormatter::emptyReport();
        }
    }

    /**
     * @param  array{id: int|null, identifier: string, password: string}  $candidate
     */
    private function loginMerchant(array $candidate): ?string
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->post('https://merchant.pathao.com/api/v1/login', [
                'username' => $candidate['identifier'],
                'password' => $candidate['password'],
            ]);

        $token = trim((string) $response->json('access_token'));

        if (! $response->successful() || $token === '') {
            $this->credentials->markFailure($candidate['id'], 'Merchant login failed');
            LogHelper::saveLog('Pathao fraud check error', 'Merchant login failed for '.$candidate['identifier'].' (HTTP '.$response->status().').');

            return null;
        }

        Cache::put(
            $this->credentials->sessionCacheKey('pathao', $candidate['identifier']),
            $token,
            now()->addMinutes(self::MERCHANT_TOKEN_MINUTES),
        );
        $this->credentials->markUsed($candidate['id']);

        return $token;
    }

    /**
     * @return array{ok: bool, unauthorized: bool, busy: bool, report: array<string, mixed>}
     */
    private function readMerchantSuccess(string $phone, string $token): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($token)
            ->post('https://merchant.pathao.com/api/v1/user/success', [
                'phone' => $phone,
            ]);

        return $this->interpretMerchantHttp($response);
    }

    /**
     * @return array{ok: bool, unauthorized: bool, busy: bool, report: array<string, mixed>}
     */
    private function interpretMerchantHttp(Response $response): array
    {
        if ($response->status() === 401) {
            return [
                'ok' => false,
                'unauthorized' => true,
                'busy' => false,
                'report' => [],
            ];
        }

        if (! $response->successful()) {
            LogHelper::saveLog('Pathao fraud check error', 'Merchant success API failed with status '.$response->status());

            return [
                'ok' => false,
                'unauthorized' => false,
                'busy' => $response->status() === 429 || $response->status() >= 500,
                'report' => [],
            ];
        }

        return [
            'ok' => true,
            'unauthorized' => false,
            'busy' => false,
            'report' => $this->mapPathaoPayload([
                'data' => $response->json('data', []),
            ]),
        ];
    }

    private function mapPathaoPayload(array $pathaoResponse): array
    {
        return CourierReportFormatter::fromPathao($pathaoResponse);
    }

    private function persistHermesToken(array $tokenBody): void
    {
        $secretToken = config('pathao-courier.pathao_secret_token');

        if (!filled($secretToken)) {
            return;
        }

        $payload = [
            'token' => Arr::get($tokenBody, 'access_token'),
            'refresh_token' => Arr::get($tokenBody, 'refresh_token'),
            'expires_in' => time() + (int) Arr::get($tokenBody, 'expires_in', 0),
            'updated_at' => now(),
        ];

        $table = config('pathao-courier.pathao_db_table_name');
        $existing = DB::table($table)->where('secret_token', $secretToken)->first();

        if ($existing) {
            DB::table($table)->where('secret_token', $secretToken)->update($payload);
            return;
        }

        DB::table($table)->insert(array_merge($payload, [
            'secret_token' => $secretToken,
            'created_at' => now(),
        ]));
    }
}
