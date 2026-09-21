<?php

namespace App\Services;

use App\LogHelper;
use App\Mail\SubscriptionAlertMail;
use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SubscriptionNotificationService
{
    public function __construct(
        protected SubscriptionAlertService $alertService
    ) {
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function notifyMerchant(User $user, AccessToken $token, array $alert, ?string $domain = null): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (! config('subscription.notifications.enabled', true)) {
            return $result;
        }

        if (! $this->meetsSeverityThreshold($alert)) {
            $result['skipped']++;

            return $result;
        }

        $domain ??= app(DomainNormalizer::class)->normalize($token->domain);

        foreach ($this->enabledChannels($alert) as $channel) {
            if ($channel === 'sms' && ! $this->shouldSendExpirySms($alert)) {
                $result['skipped']++;

                continue;
            }

            if (
                in_array($channel, ['sms', 'whatsapp'], true)
                && $this->shouldSkipDuplicateLicenseExpirySms($user, $domain, $alert, $channel)
            ) {
                $result['skipped']++;

                continue;
            }

            if ($this->alertService->wasNotified($user, $domain, $alert, $channel)) {
                $result['skipped']++;

                continue;
            }

            $sent = match ($channel) {
                'email' => $this->sendEmail($user, $domain, $alert),
                'sms' => $this->sendSms($user, $domain, $alert),
                'whatsapp' => $this->sendWhatsApp($user, $domain, $alert),
                default => false,
            };

            if ($sent) {
                $this->alertService->logAlert($user, $domain, $alert, $channel);
                $result['sent']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $alert
     * @return array<int, string>
     */
    private function enabledChannels(array $alert): array
    {
        $channels = [];

        if (config('subscription.notifications.email', true)) {
            $channels[] = 'email';
        }

        if ($this->smsEnabledForAlert($alert)) {
            $channels[] = 'sms';
        }

        if (config('subscription.notifications.whatsapp', false)) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function smsEnabledForAlert(array $alert): bool
    {
        if (config('subscription.notifications.sms', false)) {
            return true;
        }

        return (bool) config('subscription.notifications.sms_expiry', true)
            && $this->isExpiryAlert($alert);
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function isExpiryAlert(array $alert): bool
    {
        return in_array($alert['type'] ?? '', [
            'subscription_expiring',
            'subscription_expired',
            'license_expiring',
            'license_expired',
        ], true);
    }

    /**
     * Early SMS only on configured milestones (default 7, 3, 1, 0 days).
     *
     * @param  array<string, mixed>  $alert
     */
    private function shouldSendExpirySms(array $alert): bool
    {
        if (! $this->isExpiryAlert($alert)) {
            return true;
        }

        if (in_array($alert['type'] ?? '', ['subscription_expired', 'license_expired'], true)) {
            return true;
        }

        $days = $alert['days_remaining'] ?? null;
        if ($days === null) {
            return false;
        }

        $milestones = array_map('intval', config('subscription.notifications.sms_expiry_days', [7, 3, 1, 0]));

        return in_array((int) $days, $milestones, true);
    }

    /**
     * Package and license usually end the same day — send the plan SMS only.
     *
     * @param  array<string, mixed>  $alert
     */
    private function shouldSkipDuplicateLicenseExpirySms(User $user, ?string $domain, array $alert, string $channel): bool
    {
        $type = $alert['type'] ?? '';

        if (! in_array($type, ['license_expiring', 'license_expired'], true)) {
            return false;
        }

        $planAlert = [
            'type' => $type === 'license_expired' ? 'subscription_expired' : 'subscription_expiring',
        ];

        return $this->alertService->wasNotified($user, $domain, $planAlert, $channel);
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function meetsSeverityThreshold(array $alert): bool
    {
        $min = config('subscription.notifications.min_severity', 'warning');
        $rank = fn (string $severity) => match ($severity) {
            'danger' => 3,
            'warning' => 2,
            default => 1,
        };

        return $rank($alert['severity'] ?? 'info') >= $rank($min);
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function sendEmail(User $user, ?string $domain, array $alert): bool
    {
        if (! $user->email) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new SubscriptionAlertMail($user, $domain, $alert));

            return true;
        } catch (\Throwable $th) {
            LogHelper::saveLog('subscription email failed', $th->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function sendSms(User $user, ?string $domain, array $alert): bool
    {
        $phone = $user->phone;
        if (! $phone) {
            return false;
        }

        $message = $this->formatSmsMessage($user, $domain, $alert);
        $result = app(BulkSmsService::class)->send($phone, $message);

        return $result['ok'];
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function sendWhatsApp(User $user, ?string $domain, array $alert): bool
    {
        $webhookUrl = config('subscription.notifications.whatsapp_webhook_url');
        $phone = $user->whatsapp_phone ?: $user->phone;

        if (! $webhookUrl) {
            LogHelper::saveLog('subscription whatsapp skipped', 'SUBSCRIPTION_WHATSAPP_WEBHOOK_URL is not set while WhatsApp notifications are enabled.');

            return false;
        }

        if (! $phone) {
            return false;
        }

        try {
            $response = Http::timeout(15)->post($webhookUrl, [
                'phone' => $phone,
                'message' => $this->formatSmsMessage($user, $domain, $alert),
                'domain' => $domain,
                'alert_type' => $alert['type'] ?? null,
                'severity' => $alert['severity'] ?? null,
                'merchant_id' => $user->id,
            ]);

            if (! $response->successful()) {
                LogHelper::saveLog('subscription whatsapp failed', $response->body());

                return false;
            }

            return true;
        } catch (\Throwable $th) {
            LogHelper::saveLog('subscription whatsapp failed', $th->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function formatSmsMessage(User $user, ?string $domain, array $alert): string
    {
        $shop = $domain ?: 'আপনার স্টোর';
        $phone = trim((string) config('subscription.notifications.sms_support_phone', '01770989591'));
        $type = $alert['type'] ?? '';
        $days = (int) ($alert['days_remaining'] ?? 0);
        $isLicense = in_array($type, ['license_expiring', 'license_expired'], true);
        $subject = $isLicense ? 'লাইসেন্স টোকেনের' : 'প্ল্যানের';
        $renewItem = $isLicense ? 'টোকেনটি' : 'প্ল্যানটি';

        return match ($type) {
            'subscription_expiring', 'license_expiring' => $days === 0
                ? "WooEasyLife: {$shop} {$subject} মেয়াদ আজ শেষ হবে। সেবা চালু রাখতে এখনই Renew করুন। যেকোনো প্রয়োজনে কল করুন: {$phone}"
                : "WooEasyLife: {$shop} {$subject} মেয়াদ {$days} দিনের মধ্যে শেষ হবে। সেবা চালু রাখতে সময়মতো {$renewItem} Renew করুন।",
            'subscription_expired', 'license_expired' => "WooEasyLife: {$shop} {$subject} মেয়াদ শেষ। সেবা চালু করতে এখনই Renew করুন। প্রয়োজনে কল করুন: {$phone}",
            default => 'WooEasyLife: '.trim((string) ($alert['message'] ?? 'সাবস্ক্রিপশন আপডেট')).($domain ? " ({$domain})" : ''),
        };
    }
}
