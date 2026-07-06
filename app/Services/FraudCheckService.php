<?php

namespace App\Services;

use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
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
        private MerchantSteadfastFraudCredentialResolver $steadfastCredentialResolver,
    ) {}

    public function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D/', '', (string) $phone);

        if (str_starts_with($phone, '880')) {
            $phone = '0' . substr($phone, 2);
        }

        if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
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

        $steadfastResponse = $this->steadfastFraudChecker->check($phone, $steadfastCredentials);
        $pathaoResponse = $this->pathaoFraudChecker->check($phone);
        $paperFlyResponse = $this->paperflyFraudChecker->check($phone);

        $steadfastResponse = $this->withCourierStatus($steadfastResponse, 'Steadfast');
        $pathaoResponse = $this->withCourierStatus($pathaoResponse, 'Pathao');
        $paperFlyResponse = $this->withCourierStatus($paperFlyResponse, 'Paperfly');

        $totals = CourierReportFormatter::aggregateTotals(
            $steadfastResponse,
            $pathaoResponse,
            $paperFlyResponse,
        );

        $totalOrder = (int) ceil($totals['total_order']);
        $confirmOrder = (int) ceil($totals['confirmed']);
        $cancelOrder = (int) ceil($totals['cancel']);

        return [
            'total_order' => $totalOrder,
            'confirmed' => $confirmOrder,
            'frauds' => $steadfastResponse['frauds'] ?? [],
            'cancel' => $cancelOrder,
            'success_rate' => $this->resolveSuccessRate(
                $totalOrder,
                $confirmOrder,
                $steadfastResponse,
                $pathaoResponse,
                $paperFlyResponse,
            ),
            'courier' => [
                ['title' => 'Stead Fast', 'report' => $steadfastResponse],
                ['title' => 'Pathao', 'report' => $pathaoResponse],
                ['title' => 'Paper Fly', 'report' => $paperFlyResponse],
            ],
        ];
    }

    public function checkMultiple(array $numbers): array
    {
        $users = [];

        foreach ($numbers as $number) {
            $phone = is_array($number) ? ($number['phone'] ?? null) : null;

            if (!$phone) {
                continue;
            }

            $users[] = [...$number, 'report' => $this->getReport($phone)];
        }

        return $users;
    }

    public function expireSessions(): array
    {
        $steadfastExpired = $this->steadfastFraudChecker->expireSession();
        $paperflyExpired = $this->paperflyFraudChecker->expireToken();

        return [
            'message' => 'Courier sessions expired. The next fraud check will re-authenticate.',
            'cleared' => [
                'steadfast' => $steadfastExpired,
                'paperfly' => $paperflyExpired,
            ],
        ];
    }

    public function credentialStatus(): array
    {
        return [
            'pathao_merchant_login' => filled(config('fraud-checker-bd-courier.pathao.user'))
                && filled(config('fraud-checker-bd-courier.pathao.password')),
            'pathao_hermes_token' => filled(config('pathao-courier.pathao_secret_token'))
                && DB::table(config('pathao-courier.pathao_db_table_name'))
                    ->where('secret_token', config('pathao-courier.pathao_secret_token'))
                    ->whereNotNull('token')
                    ->exists(),
            'pathao_issue_token' => filled(config('pathao-courier.pathao_client_id'))
                && filled(config('pathao-courier.pathao_client_secret'))
                && filled(config('fraud-checker-bd-courier.pathao.user'))
                && filled(config('fraud-checker-bd-courier.pathao.password')),
            'steadfast_login' => $this->steadfastFraudChecker->isConfigured(),
            'steadfast_legacy_curl' => file_exists(SteadfastCurlExporter::path()),
            'paperfly' => $this->paperflyFraudChecker->isConfigured(),
        ];
    }

    private function resolveSuccessRate(int $totalOrder, int $confirmOrder, array ...$courierReports): string
    {
        if ($totalOrder > 0) {
            return ceil(($confirmOrder / $totalOrder) * 100) . '%';
        }

        foreach ($courierReports as $report) {
            if (!empty($report['customer_rating'])) {
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
        if (($report['total_order'] ?? 0) > 0 || !empty($report['frauds'])) {
            $report['status'] = 'ok';

            return $report;
        }

        if (($report['data_type'] ?? 'delivery') === 'rating' || !empty($report['customer_rating'])) {
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
            'Steadfast' => 'Steadfast session expired. Credentials will auto-refresh on next check.',
            'Pathao' => 'Pathao returned no delivery data for this number.',
            'Paperfly' => 'Paperfly has no delivery records for this number.',
            default => 'No data returned.',
        };

        return $report;
    }
}
