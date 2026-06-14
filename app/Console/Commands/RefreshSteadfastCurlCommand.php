<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\SteadfastCurlExporter;
use App\Services\FraudCheck\SteadfastNativeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshSteadfastCurlCommand extends Command
{
    protected $signature = 'steadfast:refresh-curl {phone=01770989591}';

    protected $description = 'Verify or refresh curlcode.txt for Steadfast fraud check';

    public function handle(SteadfastNativeClient $client): int
    {
        $phone = (string) $this->argument('phone');

        if ($this->verifyExistingCurl($phone)) {
            $this->info('Existing curl session is still valid: ' . SteadfastCurlExporter::path());

            return self::SUCCESS;
        }

        $email = config('fraud-checker-bd-courier.steadfast.user');
        $password = config('fraud-checker-bd-courier.steadfast.password');

        if (!filled($email) || !filled($password)) {
            $this->error('STEADFAST_USER and STEADFAST_PASSWORD must be set in .env');

            return self::FAILURE;
        }

        $host = SteadfastNativeClient::DEFAULT_HOST;
        $this->info("Trying one login on {$host}...");

        try {
            $cookies = $client->login($host, (string) $email, (string) $password);
            $raw = $client->fetchFraudCheckRaw($host, $cookies, $phone);

            SteadfastCurlExporter::save($host, $cookies, $phone);

            Cache::put(
                'fraud_check_steadfast_session_' . md5((string) $email),
                ['host' => $host, 'cookies' => $cookies],
                now()->addMinutes(55)
            );

            $report = CourierReportFormatter::fromSteadfast(json_decode($raw, true) ?? []);
            $this->info("Saved: " . SteadfastCurlExporter::path());
            $this->info("Verified: {$report['total_order']} orders, " . count($report['frauds'] ?? []) . ' fraud reports');

            return self::SUCCESS;
        } catch (\Throwable $th) {
            $this->error("{$host}: {$th->getMessage()}");
        }

        $this->line('Steadfast is blocking automated login from this server.');
        $this->line('Log into https://www.steadfast.com.bd in Chrome, run a fraud check, copy the request as cURL, and paste it on the Fraud Check Expire page.');

        return self::FAILURE;
    }

    private function verifyExistingCurl(string $phone): bool
    {
        if (!file_exists(SteadfastCurlExporter::path())) {
            return false;
        }

        $raw = SteadfastCurlExporter::run($phone);

        if (!SteadfastCurlExporter::isValid($raw)) {
            return false;
        }

        $report = CourierReportFormatter::fromSteadfast(json_decode($raw, true) ?? []);
        $this->info("Verified existing curl: {$report['total_order']} orders, " . count($report['frauds'] ?? []) . ' fraud reports');

        return true;
    }
}
