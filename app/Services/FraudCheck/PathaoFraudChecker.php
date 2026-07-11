<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Enan\PathaoCourier\Facades\PathaoCourier;
use Enan\PathaoCourier\Requests\PathaoUserSuccessRateRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PathaoFraudChecker
{
    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
    ) {}

    public function check(string $phone): array
    {
        if ($this->hasMerchantCredentials()) {
            $report = $this->checkViaMerchantPortal($phone);

            if ($this->hasDeliveryData($report)) {
                return $report;
            }
        }

        if ($this->hasHermesTokenInDatabase()) {
            $report = $this->checkViaHermes($phone);

            if ($this->hasDeliveryData($report)) {
                return $report;
            }
        }

        if ($this->hasHermesIssueTokenCredentials()) {
            $report = $this->checkViaHermesIssueToken($phone);

            if ($this->hasDeliveryData($report)) {
                return $report;
            }
        }

        LogHelper::saveLog('Pathao fraud check skipped', 'Pathao credentials are not configured or returned no data.');

        return CourierReportFormatter::emptyReport();
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

    private function hasDeliveryData(array $report): bool
    {
        return ($report['total_order'] ?? 0) > 0
            || ($report['confirmed'] ?? 0) > 0
            || ($report['cancel'] ?? 0) > 0
            || !empty($report['customer_rating']);
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
            foreach ($this->credentials->loginCandidates('pathao') as $candidate) {
                $loginResponse = Http::timeout(30)
                    ->acceptJson()
                    ->post('https://merchant.pathao.com/api/v1/login', [
                        'username' => $candidate['identifier'],
                        'password' => $candidate['password'],
                    ]);

                $accessToken = trim((string) $loginResponse->json('access_token'));

                if (! $loginResponse->successful() || $accessToken === '') {
                    $this->credentials->markFailure($candidate['id'], 'Merchant login failed');
                    LogHelper::saveLog('Pathao fraud check error', $loginResponse->body());

                    continue;
                }

                $successResponse = Http::timeout(30)
                    ->acceptJson()
                    ->withToken($accessToken)
                    ->post('https://merchant.pathao.com/api/v1/user/success', [
                        'phone' => $phone,
                    ]);

                if (! $successResponse->successful()) {
                    $this->credentials->markFailure(
                        $candidate['id'],
                        'Merchant success API failed HTTP '.$successResponse->status(),
                    );
                    LogHelper::saveLog('Pathao fraud check error', 'Merchant success API failed with status '.$successResponse->status());

                    continue;
                }

                $this->credentials->markSuccess($candidate['id']);

                return $this->mapPathaoPayload([
                    'data' => $successResponse->json('data', []),
                ]);
            }

            return CourierReportFormatter::emptyReport();
        } catch (\Throwable $th) {
            LogHelper::saveLog('Pathao fraud check error', $th->getMessage());

            return CourierReportFormatter::emptyReport();
        }
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
