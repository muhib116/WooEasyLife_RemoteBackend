<?php

namespace App\Services;

use App\Services\FraudCheck\CarrybeeFraudChecker;
use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\RedxFraudChecker;
use App\Services\FraudCheck\SteadfastCurlExporter;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        $phone = preg_replace('/\D/', '', (string) $phone);

        if (str_starts_with($phone, '880')) {
            $phone = '0' . substr($phone, 2);
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

        $steadfastResponse = $this->withCourierStatus(
            $this->steadfastFraudChecker->check($phone, $steadfastCredentials),
            'Steadfast',
        );
        $pathaoResponse = $this->withCourierStatus(
            $this->pathaoFraudChecker->check($phone),
            'Pathao',
        );
        $paperFlyResponse = $this->withCourierStatus(
            $this->paperflyFraudChecker->check($phone),
            'Paperfly',
        );

        $courier = [
            ['title' => 'Stead Fast', 'report' => $steadfastResponse],
            ['title' => 'Pathao', 'report' => $pathaoResponse],
            ['title' => 'Paper Fly', 'report' => $paperFlyResponse],
        ];

        $aggregateReports = [$steadfastResponse, $pathaoResponse, $paperFlyResponse];
        $successRateReports = [$steadfastResponse, $pathaoResponse, $paperFlyResponse];
        $carrybeeResponse = null;

        if (config('fraud_check.include_redx', true)) {
            $redxResponse = $this->withCourierStatus(
                $this->redxFraudChecker->check($phone),
                'RedX',
            );
            $courier[] = ['title' => 'RedX', 'report' => $redxResponse];
            $successRateReports[] = $redxResponse;

            if (config('fraud_check.aggregate_redx', true)) {
                $aggregateReports[] = $redxResponse;
            }
        }

        if (config('fraud_check.include_carrybee', true)) {
            $carrybeeResponse = $this->withCourierStatus(
                $this->carrybeeFraudChecker->check($phone),
                'Carrybee',
            );
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
            $phone = is_array($number) ? ($number['phone'] ?? null) : null;

            if (! $phone) {
                continue;
            }

            $users[] = [...$number, 'report' => $this->getReport($phone)];
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

        $report['status'] = 'unavailable';
        $report['message'] = match ($courier) {
            'Steadfast' => ! empty($report['credential_error'])
                ? 'Steadfast portal credentials are invalid or expired. Update them in courier settings.'
                : 'Steadfast session expired. Credentials will auto-refresh on next check.',
            'Pathao' => 'Pathao returned no delivery data for this number.',
            'Paperfly' => 'Paperfly has no delivery records for this number.',
            'RedX' => $report['message'] ?? 'RedX returned no delivery data for this number.',
            'Carrybee' => $report['message'] ?? 'Carrybee returned no data for this number.',
            default => 'No data returned.',
        };

        return $report;
    }
}
