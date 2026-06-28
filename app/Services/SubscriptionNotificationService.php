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

        foreach ($this->enabledChannels() as $channel) {
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
     * @return array<int, string>
     */
    private function enabledChannels(): array
    {
        $channels = [];

        if (config('subscription.notifications.email', true)) {
            $channels[] = 'email';
        }

        if (config('subscription.notifications.sms', false)) {
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

        $apiKey = config('services.bulksms.api_key');
        if (! $apiKey) {
            return false;
        }

        $message = $this->formatSmsMessage($user, $domain, $alert);

        try {
            $response = Http::timeout(15)->get('http://bulksmsbd.net/api/smsapi', [
                'api_key' => $apiKey,
                'type' => 'text',
                'number' => $phone,
                'senderid' => config('services.bulksms.sender_id'),
                'message' => $message,
            ]);

            if (! $response->successful()) {
                LogHelper::saveLog('subscription sms failed', $response->body());

                return false;
            }

            return true;
        } catch (\Throwable $th) {
            LogHelper::saveLog('subscription sms failed', $th->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function sendWhatsApp(User $user, ?string $domain, array $alert): bool
    {
        $webhookUrl = config('subscription.notifications.whatsapp_webhook_url');
        $phone = $user->whatsapp_phone ?: $user->phone;

        if (! $webhookUrl || ! $phone) {
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
        $portalUrl = rtrim((string) config('subscription.notifications.portal_url'), '/') . '/portal/billing';
        $domainPart = $domain ? " ({$domain})" : '';

        return config('app.name') . ": {$alert['message']}{$domainPart}. Renew: {$portalUrl}";
    }
}
