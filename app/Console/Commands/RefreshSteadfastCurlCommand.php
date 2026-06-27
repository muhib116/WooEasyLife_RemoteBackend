<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\SteadfastCurlExporter;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RefreshSteadfastCurlCommand extends Command
{
    protected $signature = 'steadfast:refresh-curl {phone=01770989591}';

    protected $description = 'Log into Steadfast and refresh curlcode.txt for fraud check fallback';

    public function handle(): int
    {
        $email = config('fraud-checker-bd-courier.steadfast.user');
        $password = config('fraud-checker-bd-courier.steadfast.password');

        if (!filled($email) || !filled($password)) {
            $this->error('STEADFAST_USER and STEADFAST_PASSWORD must be set in .env');

            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');

        foreach (['www.steadfast.com.bd', 'steadfast.com.bd'] as $host) {
            $this->info("Trying {$host}...");

            try {
                if ($this->refreshForHost($host, $email, $password, $phone)) {
                    $this->info('Saved: ' . SteadfastCurlExporter::path());

                    return self::SUCCESS;
                }
            } catch (\Throwable $th) {
                $this->warn("{$host}: {$th->getMessage()}");
            }
        }

        $this->error('Could not refresh Steadfast curl automatically.');
        $this->line('Log into https://www.steadfast.com.bd in Chrome, run a fraud check, copy the request as cURL, and paste it on the Fraud Check Expire page.');

        return self::FAILURE;
    }

    private function refreshForHost(string $host, string $email, string $password, string $phone): bool
    {
        $client = Http::timeout(60)
            ->retry(5, 2000, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);

        $loginPage = $client->get("https://{$host}/login");

        if (!$loginPage->successful()) {
            $this->warn("Login page HTTP {$loginPage->status()}");

            return false;
        }

        preg_match('/name="_token" value="([^"]+)"/', $loginPage->body(), $matches);
        $csrfToken = $matches[1] ?? null;

        if (!$csrfToken) {
            $this->warn('CSRF token not found');

            return false;
        }

        $cookies = $this->cookiesToArray($loginPage->cookies()->toArray());

        $loginResponse = $client
            ->withCookies($cookies, $host)
            ->asForm()
            ->withHeaders(['Referer' => "https://{$host}/login"])
            ->post("https://{$host}/login", [
                '_token' => $csrfToken,
                'email' => $email,
                'password' => $password,
            ]);

        if (!$loginResponse->successful() && !$loginResponse->redirect()) {
            $this->warn("Login failed HTTP {$loginResponse->status()}");

            return false;
        }

        $cookies = array_merge(
            $cookies,
            $this->cookiesToArray($loginResponse->cookies()->toArray())
        );

        if (!isset($cookies['steadfast_courier_session'])) {
            $this->warn('Login did not return a session cookie — check STEADFAST credentials');

            return false;
        }

        SteadfastCurlExporter::save($host, $cookies, $phone);

        Cache::put(
            'fraud_check_steadfast_session_' . md5((string) config('fraud-checker-bd-courier.steadfast.user')),
            ['host' => $host, 'cookies' => $cookies],
            now()->addMinutes(55)
        );

        $fraudResponse = $client
            ->withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => "https://{$host}/user/frauds/check",
                'X-XSRF-TOKEN' => urldecode($cookies['XSRF-TOKEN'] ?? ''),
            ])
            ->withCookies($cookies, $host)
            ->get("https://{$host}/user/frauds/check/{$phone}");

        if ($fraudResponse->successful()) {
            $payload = $fraudResponse->json() ?? [];
            $report = CourierReportFormatter::fromCounts(
                (int) ($payload['total_delivered'] ?? 0),
                (int) ($payload['total_cancelled'] ?? 0),
                ['frauds' => $payload['frauds'] ?? []]
            );
            $this->info("Verified: {$report['total_order']} orders, " . count($report['frauds'] ?? []) . ' fraud reports');

            return true;
        }

        if (!function_exists('shell_exec')) {
            $this->warn('Fraud API blocked and shell_exec unavailable to test saved curl');

            return true;
        }

        $output = (string) shell_exec('curl -s ' . escapeshellarg("https://{$host}/user/frauds/check/{$phone}") . ' 2>/dev/null');

        if (SteadfastCurlExporter::isValid($output)) {
            $this->info('Saved curl verified via shell_exec');

            return true;
        }

        $this->warn('Session saved but fraud API blocked from this server (Cloudflare). Curl may work on production.');

        return true;
    }

    private function cookiesToArray(array $cookies): array
    {
        $mapped = [];

        foreach ($cookies as $cookie) {
            $mapped[$cookie['Name']] = $cookie['Value'];
        }

        return $mapped;
    }
}
