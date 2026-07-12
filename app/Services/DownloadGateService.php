<?php

namespace App\Services;

use App\LogHelper;
use App\Models\DownloadLead;
use App\Services\Employee\EmployeePhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DownloadGateService
{
    private const OTP_TTL_SECONDS = 300;

    private const OTP_RESEND_SECONDS = 60;

    private const TOKEN_TTL_MINUTES = 30;

    private const MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        private DomainNormalizer $domainNormalizer,
    ) {}

    /**
     * @return array{ok: bool, message: string, resend_after?: int, website?: string}
     */
    public function sendOtp(string $name, string $phone, string $website, string $ip): array
    {
        $name = trim($name);
        $phone = $this->normalizeAndValidatePhone($phone);
        $website = $this->normalizeAndValidateWebsite($website);

        if ($name === '' || mb_strlen($name) < 2) {
            throw new InvalidArgumentException('নাম কমপক্ষে ২ অক্ষরের হতে হবে।');
        }

        $cacheKey = $this->otpCacheKey($phone);
        $existing = Cache::get($cacheKey);

        if (is_array($existing) && isset($existing['sent_at'])) {
            $elapsed = time() - (int) $existing['sent_at'];
            $wait = self::OTP_RESEND_SECONDS - $elapsed;

            if ($wait > 0) {
                return [
                    'ok' => false,
                    'message' => "OTP আবার পাঠাতে {$wait} সেকেন্ড অপেক্ষা করুন।",
                    'resend_after' => $wait,
                ];
            }
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($cacheKey, [
            'hash' => hash('sha256', $code),
            'name' => $name,
            'website' => $website,
            'attempts' => 0,
            'sent_at' => time(),
            'ip' => $ip,
        ], self::OTP_TTL_SECONDS);

        $sent = $this->sendSms(
            $phone,
            "WooEasyLife OTP: {$code}. এটা কারো সাথে শেয়ার করবেন না। {$code} কোডটি ".((int) (self::OTP_TTL_SECONDS / 60)).' মিনিটের জন্য বৈধ।',
        );

        if (! $sent) {
            LogHelper::saveLog('download gate otp sms failed', "phone={$phone}");

            $hasGateway = filled(config('services.bulksms.api_key'));

            if ($hasGateway || ! app()->environment(['local', 'testing'])) {
                Cache::forget($cacheKey);

                throw new InvalidArgumentException('SMS পাঠানো যায়নি। কিছুক্ষণ পর আবার চেষ্টা করুন।');
            }
        }

        $payload = [
            'ok' => true,
            'message' => 'আপনার ফোনে OTP পাঠানো হয়েছে।',
            'resend_after' => self::OTP_RESEND_SECONDS,
            'website' => $website,
        ];

        if (! filled(config('services.bulksms.api_key')) && app()->environment(['local', 'testing'])) {
            $payload['debug_otp'] = $code;
            $payload['message'] = 'লোকাল মোড: OTP SMS ছাড়াই জেনারেট হয়েছে।';
        }

        return $payload;
    }

    /**
     * @return array{ok: bool, message: string, download_token?: string, expires_in?: int, website?: string}
     */
    public function verifyOtp(string $phone, string $otp, string $ip, ?string $userAgent = null): array
    {
        $phone = $this->normalizeAndValidatePhone($phone);
        $otp = preg_replace('/\D+/', '', trim($otp)) ?? '';

        if (strlen($otp) !== 6) {
            throw new InvalidArgumentException('৬ সংখ্যার OTP দিন।');
        }

        $cacheKey = $this->otpCacheKey($phone);
        $existing = Cache::get($cacheKey);

        if (! is_array($existing) || empty($existing['hash'])) {
            throw new InvalidArgumentException('OTP মেয়াদ শেষ বা পাওয়া যায়নি। আবার OTP পাঠান।');
        }

        $attempts = (int) ($existing['attempts'] ?? 0);

        if ($attempts >= self::MAX_OTP_ATTEMPTS) {
            Cache::forget($cacheKey);

            throw new InvalidArgumentException('অনেকবার ভুল OTP দিয়েছেন। নতুন OTP পাঠান।');
        }

        if (! hash_equals((string) $existing['hash'], hash('sha256', $otp))) {
            $existing['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $existing, self::OTP_TTL_SECONDS);

            throw new InvalidArgumentException('OTP সঠিক নয়। আবার চেষ্টা করুন।');
        }

        $name = trim((string) ($existing['name'] ?? 'Customer'));
        $website = trim((string) ($existing['website'] ?? ''));
        Cache::forget($cacheKey);

        $token = Str::random(48);

        $lead = DownloadLead::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'website' => $website !== '' ? $website : null,
                'phone_verified_at' => now(),
                'download_token' => $token,
                'download_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            ],
        );

        return [
            'ok' => true,
            'message' => 'ফোন নম্বর যাচাই হয়েছে। এখন ডাউনলোড করতে পারবেন।',
            'download_token' => $lead->download_token,
            'expires_in' => self::TOKEN_TTL_MINUTES * 60,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'website' => $lead->website,
        ];
    }

    public function resolveLeadByToken(string $token): DownloadLead
    {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException('ডাউনলোড টোকেন লাগবে।');
        }

        $lead = DownloadLead::query()->where('download_token', $token)->first();

        if (! $lead || ! $lead->hasValidDownloadToken()) {
            throw new InvalidArgumentException('ডাউনলোড লিংকের মেয়াদ শেষ। আবার OTP দিয়ে যাচাই করুন।');
        }

        return $lead;
    }

    public function markDownloaded(DownloadLead $lead, string $asset): void
    {
        $lead->forceFill([
            'downloads_count' => (int) $lead->downloads_count + 1,
            'last_download_at' => now(),
            'last_asset' => $asset,
        ])->save();
    }

    public function normalizeAndValidatePhone(string $phone): string
    {
        $normalized = EmployeePhoneNormalizer::normalize($phone);

        if (! preg_match('/^01[3-9]\d{8}$/', $normalized)) {
            throw new InvalidArgumentException('সঠিক বাংলাদেশি মোবাইল নম্বর দিন (01XXXXXXXXX)।');
        }

        return $normalized;
    }

    public function normalizeAndValidateWebsite(string $website): string
    {
        $domain = $this->domainNormalizer->normalize($website);

        if ($domain === null || $domain === '') {
            throw new InvalidArgumentException('সঠিক ওয়েবসাইট/ডোমেইন দিন (যেমন: shop.example.com)।');
        }

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            if (app()->environment(['local', 'testing']) && in_array($domain, ['127.0.0.1', '::1'], true)) {
                return $domain;
            }

            throw new InvalidArgumentException('IP অ্যাড্রেস নয় — আপনার ওয়েবসাইটের ডোমেইন দিন।');
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)
            && ! (app()->environment(['local', 'testing']) && $domain === 'localhost')) {
            throw new InvalidArgumentException('সঠিক ডোমেইন ফরম্যাট দিন (যেমন: example.com)।');
        }

        if (! $this->domainResolves($domain)) {
            throw new InvalidArgumentException('এই ডোমেইনটি আসল/লাইভ নয় বলে মনে হচ্ছে (DNS পাওয়া যায়নি)। সঠিক ওয়েবসাইট দিন।');
        }

        return $domain;
    }

    private function domainResolves(string $host): bool
    {
        if (app()->environment(['local', 'testing']) && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        if ($this->hostHasDnsRecords($host)) {
            return true;
        }

        if (str_starts_with($host, 'www.')) {
            return $this->hostHasDnsRecords(substr($host, 4));
        }

        return $this->hostHasDnsRecords('www.'.$host);
    }

    private function hostHasDnsRecords(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if ($this->domainNormalizer->hasDnsARecord($host)) {
            return true;
        }

        $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
        if ($aaaa !== []) {
            return true;
        }

        $cname = @dns_get_record($host, DNS_CNAME) ?: [];

        return $cname !== [];
    }

    private function otpCacheKey(string $phone): string
    {
        return 'download_gate_otp:'.$phone;
    }

    private function sendSms(string $phone, string $message): bool
    {
        $apiKey = config('services.bulksms.api_key');

        if (! $apiKey) {
            return false;
        }

        try {
            $response = Http::timeout(15)->get('http://bulksmsbd.net/api/smsapi', [
                'api_key' => $apiKey,
                'type' => 'text',
                'number' => $phone,
                'senderid' => config('services.bulksms.sender_id'),
                'message' => $message,
            ]);

            if (! $response->successful()) {
                LogHelper::saveLog('download gate sms failed', $response->body());

                return false;
            }

            return true;
        } catch (\Throwable $th) {
            LogHelper::saveLog('download gate sms failed', $th->getMessage());

            return false;
        }
    }
}
