<?php

namespace App\Services;

use App\Services\FraudCheck\CarrybeeFraudChecker;
use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\CourierTransport;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\RedxFraudChecker;
use App\LogHelper;
use App\Services\FraudCheck\SteadfastCurlExporter;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class FraudCheckService
{
    public function __construct(
        private SteadfastFraudChecker $steadfastFraudChecker,
        private PathaoFraudChecker $pathaoFraudChecker,
        private PaperflyFraudChecker $paperflyFraudChecker,
        private RedxFraudChecker $redxFraudChecker,
        private CarrybeeFraudChecker $carrybeeFraudChecker,
        private MerchantSteadfastFraudCredentialResolver $steadfastCredentialResolver,
    ) {}

    public function normalizePhone(?string $phone): string
    {
        $raw = strtr((string) $phone, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $phone = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($phone, '00880')) {
            $phone = substr($phone, 4);
        } elseif (str_starts_with($phone, '880')) {
            $phone = substr($phone, 2);
        } elseif (strlen($phone) === 12 && preg_match('/^881[3-9]\d{8}$/', $phone)) {
            $phone = '0'.substr($phone, 2);
        } elseif (strlen($phone) === 10 && preg_match('/^1[3-9]\d{8}$/', $phone)) {
            $phone = '0'.$phone;
        }

        if (! preg_match('/^01[3-9]\d{8}$/', $phone)) {
            throw new InvalidArgumentException('The provided phone number is invalid. Use a Bangladesh mobile number like 017XXXXXXXX.');
        }

        return $phone;
    }

    public function getReport(string $phone, ?array $steadfastCredentials = null): array
    {
        $phone = $this->normalizePhone($phone);

        if ($steadfastCredentials === null) {
            $steadfastCredentials = $this->steadfastCredentialResolver->resolveFromCurrentRequest();
        }

        $raw = $this->fetchCourierReports($phone, $steadfastCredentials);

        $steadfastResponse = $this->withCourierStatus($raw['steadfast'], 'Steadfast');
        $pathaoResponse = $this->withCourierStatus($raw['pathao'], 'Pathao');
        $paperFlyResponse = $this->withCourierStatus($raw['paperfly'], 'Paperfly');

        $courier = [
            ['title' => 'Stead Fast', 'report' => $steadfastResponse],
            ['title' => 'Pathao', 'report' => $pathaoResponse],
            ['title' => 'Paper Fly', 'report' => $paperFlyResponse],
        ];

        $aggregateReports = [$steadfastResponse, $pathaoResponse, $paperFlyResponse];
        $successRateReports = [$steadfastResponse, $pathaoResponse, $paperFlyResponse];
        $carrybeeResponse = null;

        if (config('fraud_check.include_redx', true)) {
            $redxResponse = $this->withCourierStatus($raw['redx'], 'RedX');
            $courier[] = ['title' => 'RedX', 'report' => $redxResponse];
            $successRateReports[] = $redxResponse;

            if (config('fraud_check.aggregate_redx', true)) {
                $aggregateReports[] = $redxResponse;
            }
        }

        if (config('fraud_check.include_carrybee', true)) {
            $carrybeeResponse = $this->withCourierStatus($raw['carrybee'], 'Carrybee');
            $courier[] = ['title' => 'Carrybee', 'report' => $carrybeeResponse];
            $successRateReports[] = $carrybeeResponse;

            if (config('fraud_check.aggregate_carrybee', true)) {
                $aggregateReports[] = $carrybeeResponse;
            }
        }

        $totals = CourierReportFormatter::aggregateTotals(...$aggregateReports);

        $totalOrder = (int) ceil($totals['total_order']);
        $confirmOrder = (int) ceil($totals['confirmed']);
        $cancelOrder = (int) ceil($totals['cancel']);
        $carrybeeFraudsCount = (int) ($carrybeeResponse['frauds_count'] ?? 0);

        return [
            'total_order' => $totalOrder,
            'confirmed' => $confirmOrder,
            'frauds' => $this->mergeFrauds($steadfastResponse['frauds'] ?? [], $carrybeeFraudsCount),
            'cancel' => $cancelOrder,
            'success_rate' => $this->resolveSuccessRate($totalOrder, $confirmOrder, ...$successRateReports),
            'carrybee_frauds_count' => $carrybeeFraudsCount,
            'courier' => $courier,
        ];
    }

    /**
     * Run independent courier lookups together when each one already has a
     * saved session or API key. Anything that cannot be pooled uses check(),
     * which is the same path a single fraud check used before.
     *
     * @param  array{username?: string, password?: string, api_key?: string, secret_key?: string}|null  $steadfastCredentials
     * @return array<string, array<string, mixed>>
     */
    private function fetchCourierReports(string $phone, ?array $steadfastCredentials): array
    {
        $startedAt = microtime(true);
        $jobs = [
            'steadfast' => [$this->steadfastFraudChecker, 'check', [$phone, $steadfastCredentials]],
            'pathao' => [$this->pathaoFraudChecker, 'check', [$phone]],
            'paperfly' => [$this->paperflyFraudChecker, 'check', [$phone]],
        ];

        if (config('fraud_check.include_redx', true)) {
            $jobs['redx'] = [$this->redxFraudChecker, 'check', [$phone]];
        }

        if (config('fraud_check.include_carrybee', true)) {
            $jobs['carrybee'] = [$this->carrybeeFraudChecker, 'check', [$phone]];
        }

        $plans = [];
        if (config('fraud_check.parallel_couriers', true)) {
            foreach ($jobs as $key => [$checker]) {
                $plan = $this->poolPlanFor($checker, $phone, $steadfastCredentials);
                if ($plan !== null) {
                    $plans[$key] = $plan;
                }
            }
        }

        $reports = [];
        if ($plans !== []) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($plans) {
                    foreach ($plans as $key => $plan) {
                        $plan['send']($pool, $key);
                    }
                });

                foreach ($plans as $key => $plan) {
                    $parsed = $this->readPooled($plan, is_array($responses) ? ($responses[$key] ?? null) : null);
                    if (is_array($parsed)) {
                        $reports[$key] = $parsed;
                    }
                }
            } catch (Throwable $exception) {
                LogHelper::saveLog('Fraud check parallel courier fetch failed', $exception->getMessage());
            }
        }

        foreach ($jobs as $key => [$checker, $method, $args]) {
            if (! array_key_exists($key, $reports)) {
                $reports[$key] = $checker->{$method}(...$args);
            }
        }

        return $this->retryFailedCouriers($jobs, $reports, $startedAt, $phone);
    }

    /**
     * Ask a courier once more after a failure that can pass, like a dropped
     * connection or a server error. When that retry fails too, the courier
     * is not retried for a short while, so an outage does not slow every check.
     *
     * @param  array<string, array{0: object, 1: string, 2: array<int, mixed>}>  $jobs
     * @param  array<string, array<string, mixed>>  $reports
     * @return array<string, array<string, mixed>>
     */
    private function retryFailedCouriers(array $jobs, array $reports, float $startedAt, string $phone): array
    {
        if (! config('fraud_check.courier_retry.enabled', true)) {
            return $reports;
        }

        $budgetSeconds = (float) config('fraud_check.courier_retry.budget_seconds', 20);
        $pauseMs = max(0, (int) config('fraud_check.courier_retry.pause_ms', 700));
        $cooldownSeconds = max(1, (int) config('fraud_check.courier_retry.cooldown_seconds', 60));
        $paused = false;

        foreach ($jobs as $key => [$checker, $method, $args]) {
            if (! self::isTransientFailure($reports[$key] ?? [])) {
                continue;
            }

            if (microtime(true) - $startedAt >= $budgetSeconds) {
                break;
            }

            $coolingKey = 'fraud_check_courier_retry_cooling:'.$key.':'.$phone;
            if (Cache::has($coolingKey)) {
                continue;
            }

            if (! $paused && $pauseMs > 0) {
                usleep($pauseMs * 1000);
                $paused = true;
            }

            try {
                $retried = $checker->{$method}(...$args);
            } catch (Throwable $exception) {
                LogHelper::saveLog('Fraud check courier retry failed', $key.': '.CourierTransport::safeMessage($exception));
                $retried = null;
            }

            if (is_array($retried) && empty($retried['unavailable'])) {
                $reports[$key] = $retried;

                continue;
            }

            Cache::put($coolingKey, true, now()->addSeconds($cooldownSeconds));
        }

        return $reports;
    }

    /**
     * A courier that failed in a way a later attempt can fix. Rate limits,
     * rejected credentials, missing setup, and RedX's fixed sample are not.
     *
     * @param  array<string, mixed>  $report
     */
    public static function isTransientFailure(array $report): bool
    {
        return ! empty($report['unavailable'])
            && ($report['retryable'] ?? true) !== false
            && empty($report['rate_limited'])
            && empty($report['credential_error']);
    }

    /**
     * @return array{send: callable, read: callable}|null
     */
    private function poolPlanFor(object $checker, string $phone, ?array $steadfastCredentials): ?array
    {
        if (! method_exists($checker, 'poolPlan')) {
            return null;
        }

        try {
            $plan = $checker->poolPlan($phone, $steadfastCredentials);
        } catch (Throwable) {
            return null;
        }

        if (
            ! is_array($plan)
            || ! isset($plan['send'], $plan['read'])
            || ! is_callable($plan['send'])
            || ! is_callable($plan['read'])
        ) {
            return null;
        }

        return $plan;
    }

    /**
     * @param  array{send: callable, read: callable}  $plan
     * @return array<string, mixed>|null
     */
    private function readPooled(array $plan, mixed $response): ?array
    {
        if (! $response instanceof Response) {
            return null;
        }

        try {
            $parsed = $plan['read']($response);
        } catch (Throwable $exception) {
            LogHelper::saveLog('Fraud check parallel courier fetch failed', $exception->getMessage());

            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Fetch a single courier report (used by partial background refresh).
     *
     * @return array<string, mixed>
     */
    public function checkCourier(string $courier, string $phone, ?array $steadfastCredentials = null): array
    {
        $phone = $this->normalizePhone($phone);
        $courier = strtolower(trim($courier));

        if ($steadfastCredentials === null && $courier === 'steadfast') {
            $steadfastCredentials = $this->steadfastCredentialResolver->resolveFromCurrentRequest();
        }

        return match ($courier) {
            'steadfast' => $this->withCourierStatus(
                $this->steadfastFraudChecker->check($phone, $steadfastCredentials),
                'Steadfast',
            ),
            'pathao' => $this->withCourierStatus(
                $this->pathaoFraudChecker->check($phone),
                'Pathao',
            ),
            'paperfly' => $this->withCourierStatus(
                $this->paperflyFraudChecker->check($phone),
                'Paperfly',
            ),
            'redx' => $this->withCourierStatus(
                $this->redxFraudChecker->check($phone),
                'RedX',
            ),
            'carrybee' => $this->withCourierStatus(
                $this->carrybeeFraudChecker->check($phone),
                'Carrybee',
            ),
            default => CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => "Unknown courier [{$courier}].",
            ]),
        };
    }

    public function checkMultiple(array $numbers): array
    {
        $users = [];

        foreach ($numbers as $number) {
            if (! is_array($number)) {
                continue;
            }

            $phone = $number['phone'] ?? null;

            if (! $phone) {
                $users[] = [
                    ...$number,
                    'report' => [
                        'total_order' => 0,
                        'confirmed' => 0,
                        'frauds' => [],
                        'cancel' => 0,
                        'success_rate' => 'No order history found!',
                        'courier' => [],
                        'error' => 'Missing phone',
                    ],
                ];

                continue;
            }

            try {
                $users[] = [...$number, 'report' => $this->getReport((string) $phone)];
            } catch (InvalidArgumentException $e) {
                $users[] = [
                    ...$number,
                    'report' => [
                        'total_order' => 0,
                        'confirmed' => 0,
                        'frauds' => [],
                        'cancel' => 0,
                        'success_rate' => 'No order history found!',
                        'courier' => [],
                        'error' => $e->getMessage(),
                    ],
                ];
            }
        }

        return $users;
    }

    public function expireSessions(): array
    {
        return [
            'message' => 'Courier login sessions expired. Platform fraud cache (courier snapshots) was not cleared — only partner session cookies/tokens. The next live courier call will re-authenticate.',
            'cleared' => [
                'steadfast' => $this->steadfastFraudChecker->expireSession(),
                'pathao' => $this->pathaoFraudChecker->expireSession(),
                'paperfly' => $this->paperflyFraudChecker->expireToken(),
                'redx' => $this->redxFraudChecker->expireSession(),
                'carrybee' => $this->carrybeeFraudChecker->expireSession(),
            ],
            'platform_cache_cleared' => false,
        ];
    }

    public function credentialStatus(): array
    {
        $resolver = app(\App\Services\FraudCheck\FraudPartnerCredentialResolver::class);
        $pathaoConfigured = $resolver->isConfigured('pathao');

        return [
            'pathao_merchant_login' => $pathaoConfigured,
            'pathao_hermes_token' => filled(config('pathao-courier.pathao_secret_token'))
                && DB::table(config('pathao-courier.pathao_db_table_name'))
                    ->where('secret_token', config('pathao-courier.pathao_secret_token'))
                    ->whereNotNull('token')
                    ->exists(),
            'pathao_issue_token' => filled(config('pathao-courier.pathao_client_id'))
                && filled(config('pathao-courier.pathao_client_secret'))
                && $pathaoConfigured,
            'steadfast_login' => $this->steadfastFraudChecker->isConfigured(),
            'steadfast_legacy_curl' => file_exists(SteadfastCurlExporter::path()),
            'paperfly' => $this->paperflyFraudChecker->isConfigured(),
            'redx' => $this->redxFraudChecker->isConfigured(),
            'carrybee' => $this->carrybeeFraudChecker->isConfigured(),
            'include_redx' => (bool) config('fraud_check.include_redx', true),
            'include_carrybee' => (bool) config('fraud_check.include_carrybee', true),
        ];
    }

    /**
     * @param  array<int, mixed>  $steadfastFrauds
     * @return array<int, array<string, mixed>>
     */
    private function mergeFrauds(array $steadfastFrauds, int $carrybeeFraudsCount): array
    {
        $frauds = array_values(array_filter(
            $steadfastFrauds,
            fn ($fraud) => is_array($fraud),
        ));

        if ($carrybeeFraudsCount > 0) {
            $frauds[] = [
                'name' => 'Carrybee',
                'details' => "{$carrybeeFraudsCount} fraud report(s) on Carrybee",
                'courier' => 'carrybee',
                'consignment_id' => null,
                'created_at' => null,
            ];
        }

        return $frauds;
    }

    private function resolveSuccessRate(int $totalOrder, int $confirmOrder, array ...$courierReports): string
    {
        if ($totalOrder > 0) {
            return ceil(($confirmOrder / $totalOrder) * 100).'%';
        }

        foreach ($courierReports as $report) {
            if (($report['data_type'] ?? 'delivery') === 'fraud_reports') {
                continue;
            }

            if (! empty($report['customer_rating'])) {
                return CourierReportFormatter::formatRating((string) $report['customer_rating']);
            }

            $rate = $report['success_rate'] ?? '';

            if ($rate !== '' && $rate !== 'No order history found!') {
                return $rate;
            }
        }

        return 'No order history found!';
    }

    private function withCourierStatus(array $report, string $courier): array
    {
        if (
            ($report['total_order'] ?? 0) > 0
            || ! empty($report['frauds'])
            || (int) ($report['frauds_count'] ?? 0) > 0
        ) {
            $report['status'] = 'ok';

            return $report;
        }

        if (! empty($report['api_success'])) {
            $report['status'] = 'ok';
            if (empty($report['message'])) {
                $report['message'] = match ($courier) {
                    'Steadfast' => 'No delivery history found on Steadfast.',
                    'Paperfly' => 'No delivery records found on Paperfly.',
                    'RedX' => 'No delivery history found on RedX.',
                    'Carrybee' => 'No delivery history found on Carrybee.',
                    default => 'No delivery history found.',
                };
            }

            return $report;
        }

        if (($report['data_type'] ?? 'delivery') === 'fraud_reports') {
            $report['status'] = 'ok';

            return $report;
        }

        if (($report['data_type'] ?? 'delivery') === 'rating' || ! empty($report['customer_rating'])) {
            $report['status'] = 'rating_only';

            return $report;
        }

        $rate = $report['success_rate'] ?? '';

        if ($rate !== '' && $rate !== 'No order history found!') {
            $report['status'] = 'ok';

            return $report;
        }

        // A failed lookup is never a clean record. The flag keeps it out of
        // the platform cache as a fresh result.
        $report['status'] = 'unavailable';
        $report['unavailable'] = true;
        if (! array_key_exists('retryable', $report)) {
            $report['retryable'] = empty($report['credential_error']);
        }
        $report['message'] = match (true) {
            $courier === 'Steadfast' && ! empty($report['credential_error']) => 'Steadfast API credentials were rejected. Check the API key and secret in courier settings.',
            ! empty($report['message']) => (string) $report['message'],
            $courier === 'Steadfast' => 'Steadfast session expired. Credentials will auto-refresh on next check.',
            default => $courier.' did not answer this time. This number is checked again automatically.',
        };

        return $report;
    }
}
