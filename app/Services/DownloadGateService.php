<?php

namespace App\Services;

use App\Exceptions\DownloadGateFieldException;
use App\LogHelper;
use App\Models\DownloadLead;
use App\Services\Employee\EmployeePhoneNormalizer;
use Illuminate\Support\Facades\Cache;
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
        private BulkSmsService $bulkSms,
    ) {}

    /**
     * @return array{ok: bool, message: string, resend_after?: int, website?: string, debug_otp?: string}
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

        $smsResult = $this->bulkSms->send(
            $phone,
            "WooEasyLife OTP: {$code}. Do not share this code. Valid for ".(int) (self::OTP_TTL_SECONDS / 60).' minutes.',
        );

        if (! $smsResult['ok']) {
            LogHelper::saveLog(
                'download gate otp sms failed',
                "phone={$phone}; code={$smsResult['response_code']}; message={$smsResult['message']}"
            );

            // Local/testing: keep OTP so developers can continue without BulkSMS IP whitelist.
            if (app()->environment(['local', 'testing'])) {
                return [
                    'ok' => true,
                    'message' => 'লোকাল মোড: SMS পাঠানো যায়নি ('.$smsResult['message'].')। নিচের debug OTP ব্যবহার করুন।',
                    'resend_after' => self::OTP_RESEND_SECONDS,
                    'website' => $website,
                    'debug_otp' => $code,
                ];
            }

            Cache::forget($cacheKey);

            throw new InvalidArgumentException($this->userFacingSmsError($smsResult));
        }

        return [
            'ok' => true,
            'message' => 'আপনার ফোনে OTP পাঠানো হয়েছে।',
            'resend_after' => self::OTP_RESEND_SECONDS,
            'website' => $website,
        ];
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
            throw new DownloadGateFieldException('সঠিক বাংলাদেশি মোবাইল নম্বর দিন (01XXXXXXXXX)।', 'phone');
        }

        return $normalized;
    }

    public function normalizeAndValidateWebsite(string $website): string
    {
        $raw = trim($website);

        if ($raw === '') {
            throw new DownloadGateFieldException('সঠিক ওয়েবসাইট/ডোমেইন দিন (যেমন: shop.example.com)।', 'website');
        }

        // Reject full page/console links — only a store domain is accepted (same intent as merchant setup).
        if ($this->looksLikePageUrl($raw)) {
            throw new DownloadGateFieldException('শুধু ওয়েবসাইটের ডোমেইন দিন (যেমন: myshop.com), পূর্ণ পেজ লিংক নয়।', 'website');
        }

        $domain = $this->domainNormalizer->normalize($raw);

        if ($domain === null || $domain === '') {
            throw new DownloadGateFieldException('সঠিক ওয়েবসাইট/ডোমেইন দিন (যেমন: shop.example.com)।', 'website');
        }

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            if (app()->environment(['local', 'testing']) && in_array($domain, ['127.0.0.1', '::1'], true)) {
                return $domain;
            }

            throw new DownloadGateFieldException('IP অ্যাড্রেস নয় — আপনার ওয়েবসাইটের ডোমেইন দিন।', 'website');
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)
            && ! (app()->environment(['local', 'testing']) && $domain === 'localhost')) {
            throw new DownloadGateFieldException('সঠিক ডোমেইন ফরম্যাট দিন (যেমন: example.com)।', 'website');
        }

        if (! $this->domainNormalizer->resolvesPublicly($domain)) {
            throw new DownloadGateFieldException('ডোমেইনের DNS A রেকর্ড পাওয়া যায়নি। লাইভ ওয়েবসাইটের সঠিক ডোমেইন দিন।', 'website');
        }

        return $domain;
    }

    /**
     * @return array{ok: bool, message?: string, website?: string, error_field?: string}
     */
    public function validateWebsite(string $website): array
    {
        try {
            $domain = $this->normalizeAndValidateWebsite($website);

            return [
                'ok' => true,
                'website' => $domain,
                'message' => 'ডোমেইন যাচাই হয়েছে।',
            ];
        } catch (DownloadGateFieldException $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'error_field' => $e->field,
            ];
        }
    }

    private function looksLikePageUrl(string $raw): bool
    {
        $candidate = $raw;

        if (! preg_match('#^https?://#i', $candidate)) {
            if (! str_contains($candidate, '/')) {
                return false;
            }

            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);

        if (! is_array($parts)) {
            return false;
        }

        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
        $fragment = $parts['fragment'] ?? '';

        return ($path !== '' && $path !== '/') || $query !== '' || $fragment !== '';
    }

    private function otpCacheKey(string $phone): string
    {
        return 'download_gate_otp:'.$phone;
    }

    /**
     * @param  array{ok: bool, response_code: int|null, message: string|null, raw: string|null}  $smsResult
     */
    private function userFacingSmsError(array $smsResult): string
    {
        $code = $smsResult['response_code'] ?? null;

        return match ($code) {
            1032 => 'SMS সার্ভারের IP whitelist করা নেই। BulkSMSBD প্যানেলে সার্ভার IP whitelist করুন।',
            1007 => 'SMS ব্যালেন্স শেষ। অনুগ্রহ করে পরে আবার চেষ্টা করুন।',
            1002 => 'SMS Sender ID সঠিক নয়। কনফিগারেশন চেক করুন।',
            default => 'SMS পাঠানো যায়নি। কিছুক্ষণ পর আবার চেষ্টা করুন।',
        };
    }
}
