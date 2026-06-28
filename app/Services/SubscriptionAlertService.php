<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\PackagePaymentRequest;
use App\Models\SmsBalance;
use App\Models\SubscriptionAlertLog;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SubscriptionAlertService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * Plugin-facing notices for get-user (rate-limited via cache).
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function pluginNotices(User $user, AccessToken $accessToken): ?array
    {
        $alerts = $this->collectAlerts($user, $accessToken);
        $notices = [];

        foreach ($alerts as $alert) {
            if (! $this->shouldShowPluginNotice($user, $accessToken, $alert)) {
                continue;
            }

            $notices[] = [
                'type' => $alert['severity'] === 'danger' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info'),
                'message' => $alert['message'],
                'code' => $alert['type'],
            ];

            $this->markPluginNoticeShown($user, $accessToken, $alert);
        }

        return $notices === [] ? null : $notices;
    }

    public function findTokenForDomain(User $user, string $domain): ?AccessToken
    {
        return AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('status', true)
            ->orderByDesc('id')
            ->get()
            ->first(fn (AccessToken $token) => $this->domainNormalizer->matches(
                $token->domain,
                $domain
            ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectAlertsForMerchantDomain(User $user, string $domain): array
    {
        $token = $this->findTokenForDomain($user, $domain);

        if ($token) {
            return $this->collectAlerts($user, $token);
        }

        $normalizedDomain = $this->domainNormalizer->normalize($domain) ?? $domain;
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $domain
            ));

        $remainingOrder = (int) $packages->sum('remaining_order');
        $activePackage = $packages->sortByDesc('id')->first();
        $alerts = [];

        if ($remainingOrder <= 0) {
            $alerts[] = $this->alert(
                'quota_exhausted',
                'danger',
                'Your order quota is exhausted. Submit a payment request to renew your subscription.'
            );
        } elseif ($remainingOrder <= config('subscription.quota_critical_threshold', 5)) {
            $alerts[] = $this->alert(
                'quota_critical',
                'danger',
                "Only {$remainingOrder} orders remaining. Renew soon to avoid service interruption."
            );
        } elseif ($remainingOrder <= config('subscription.quota_low_threshold', 20)) {
            $alerts[] = $this->alert(
                'quota_low',
                'warning',
                "Your remaining order quota is low ({$remainingOrder} left)."
            );
        }

        if ($activePackage?->expires_at) {
            if (now()->greaterThan($activePackage->expires_at)) {
                $alerts[] = $this->alert(
                    'subscription_expired',
                    'danger',
                    'Your subscription plan has expired.'
                );
            } elseif (now()->diffInDays($activePackage->expires_at, false) <= config('subscription.subscription_expiring_days', 7)) {
                $days = max(0, (int) now()->diffInDays($activePackage->expires_at, false));
                $alerts[] = $this->alert(
                    'subscription_expiring',
                    'warning',
                    $days === 0
                        ? 'Your subscription plan expires today.'
                        : "Your subscription plan expires in {$days} day(s)."
                );
            }
        }

        $pendingPayments = PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->filter(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                $request->domain,
                $normalizedDomain
            ));

        if ($pendingPayments->isNotEmpty()) {
            $alerts[] = $this->alert(
                'payment_pending',
                'info',
                'Your payment request is pending admin approval.'
            );
        }

        return $alerts;
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, array<string, mixed>>
     */
    public function collectPortalAlerts(User $user, array $domains): array
    {
        return collect($domains)
            ->flatMap(fn (string $domain) => collect($this->collectAlertsForMerchantDomain($user, $domain))
                ->map(fn (array $alert) => [
                    ...$alert,
                    'domain' => $this->domainNormalizer->normalize($domain) ?? $domain,
                ]))
            ->unique(fn (array $alert) => ($alert['domain'] ?? '') . ':' . $alert['type'])
            ->sortByDesc(fn (array $alert) => match ($alert['severity']) {
                'danger' => 3,
                'warning' => 2,
                default => 1,
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectAlerts(User $user, AccessToken $accessToken): array
    {
        $domain = $this->domainNormalizer->normalize($accessToken->domain);
        $alerts = [];

        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $accessToken->domain
            ));

        $remainingOrder = (int) $packages->sum('remaining_order');
        $activePackage = $packages->sortByDesc('id')->first();

        if ($remainingOrder <= 0) {
            $alerts[] = $this->alert(
                'quota_exhausted',
                'danger',
                'Your order quota is exhausted. Submit a payment request to renew your subscription.'
            );
        } elseif ($remainingOrder <= config('subscription.quota_critical_threshold', 5)) {
            $alerts[] = $this->alert(
                'quota_critical',
                'danger',
                "Only {$remainingOrder} orders remaining. Renew soon to avoid service interruption."
            );
        } elseif ($remainingOrder <= config('subscription.quota_low_threshold', 20)) {
            $alerts[] = $this->alert(
                'quota_low',
                'warning',
                "Your remaining order quota is low ({$remainingOrder} left)."
            );
        }

        if ($activePackage?->expires_at) {
            if (now()->greaterThan($activePackage->expires_at)) {
                $alerts[] = $this->alert(
                    'subscription_expired',
                    'danger',
                    'Your subscription plan has expired.'
                );
            } elseif (now()->diffInDays($activePackage->expires_at, false) <= config('subscription.subscription_expiring_days', 7)) {
                $days = max(0, (int) now()->diffInDays($activePackage->expires_at, false));
                $alerts[] = $this->alert(
                    'subscription_expiring',
                    'warning',
                    $days === 0
                        ? 'Your subscription plan expires today.'
                        : "Your subscription plan expires in {$days} day(s)."
                );
            }
        }

        if ($accessToken->expires_at) {
            if (now()->greaterThan($accessToken->expires_at)) {
                $alerts[] = $this->alert(
                    'license_expired',
                    'danger',
                    'Your license key has expired.'
                );
            } elseif (now()->diffInDays($accessToken->expires_at, false) <= config('subscription.license_expiring_days', 7)) {
                $days = max(0, (int) now()->diffInDays($accessToken->expires_at, false));
                $alerts[] = $this->alert(
                    'license_expiring',
                    'warning',
                    $days === 0
                        ? 'Your license key expires today.'
                        : "Your license key expires in {$days} day(s)."
                );
            }
        }

        $pendingPayments = PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->filter(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                $request->domain,
                $accessToken->domain
            ));

        if ($pendingPayments->isNotEmpty()) {
            $alerts[] = $this->alert(
                'payment_pending',
                'info',
                'Your payment request is pending admin approval.'
            );
        }

        $smsBalance = SmsBalance::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (SmsBalance $balance) => $this->domainNormalizer->matches(
                $balance->domain,
                $accessToken->domain
            ))
            ->sum('amount');

        $smsOutCount = SmsBalance::query()
            ->where('user_id', $user->id)
            ->where('type', 'out')
            ->get()
            ->filter(fn (SmsBalance $balance) => $this->domainNormalizer->matches(
                $balance->domain,
                $accessToken->domain
            ))
            ->count();

        if ($smsBalance <= 20 && $smsOutCount > 0) {
            $alerts[] = $this->alert(
                'sms_low',
                'info',
                'Your SMS balance is less than 20TK.'
            );
        }

        return $alerts;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function adminAlertFeed(int $limit = 100): Collection
    {
        $feed = collect();

        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereHasMorph('tokenable', [User::class], fn ($query) => $query->where('role', 'user'))
            ->with('tokenable:id,name,email,phone')
            ->orderByDesc('id')
            ->get();

        foreach ($tokens as $token) {
            $user = $token->tokenable;
            if (! $user instanceof User || ! $user->canAccessPlatform()) {
                continue;
            }

            foreach ($this->collectAlerts($user, $token) as $alert) {
                $domain = $this->domainNormalizer->normalize($token->domain);
                $feed->push([
                    ...$alert,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'domain' => $domain,
                    'token_id' => $token->id,
                    'notification_channels' => $this->notificationChannelsForAlert($user, $domain, $alert),
                ]);
            }
        }

        return $feed
            ->sortByDesc(fn (array $alert) => match ($alert['severity']) {
                'danger' => 3,
                'warning' => 2,
                default => 1,
            })
            ->values()
            ->take($limit);
    }

    /**
     * @return array<string, int>
     */
    public function summarizeAdminFeed(Collection $feed): array
    {
        return [
            'total' => $feed->count(),
            'danger' => $feed->where('severity', 'danger')->count(),
            'warning' => $feed->where('severity', 'warning')->count(),
            'info' => $feed->where('severity', 'info')->count(),
        ];
    }

    public function logAlert(User $user, ?string $domain, array $alert, string $channel = 'system'): SubscriptionAlertLog
    {
        return SubscriptionAlertLog::updateOrCreate(
            ['alert_key' => $this->notificationAlertKey($user, $domain, $alert, $channel)],
            [
                'user_id' => $user->id,
                'domain' => $domain,
                'alert_type' => $alert['type'],
                'channel' => $channel,
                'payload' => $alert,
            ]
        );
    }

    public function wasNotified(User $user, ?string $domain, array $alert, string $channel): bool
    {
        return SubscriptionAlertLog::query()
            ->where('alert_key', $this->notificationAlertKey($user, $domain, $alert, $channel))
            ->exists();
    }

    public function notificationAlertKey(User $user, ?string $domain, array $alert, string $channel): string
    {
        return implode(':', [
            $user->id,
            $domain ?? 'all',
            $alert['type'],
            now()->toDateString(),
            $channel,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function notificationChannelsForAlert(User $user, ?string $domain, array $alert): array
    {
        $channels = [];

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            if ($this->wasNotified($user, $domain, $alert, $channel)) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    private function alert(string $type, string $severity, string $message): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    private function shouldShowPluginNotice(User $user, AccessToken $accessToken, array $alert): bool
    {
        if (in_array($alert['type'], ['quota_exhausted', 'subscription_expired', 'license_expired'], true)) {
            return true;
        }

        return ! Cache::has($this->pluginNoticeCacheKey($user, $accessToken, $alert['type']));
    }

    private function markPluginNoticeShown(User $user, AccessToken $accessToken, array $alert): void
    {
        if (in_array($alert['type'], ['quota_exhausted', 'subscription_expired', 'license_expired'], true)) {
            return;
        }

        Cache::put(
            $this->pluginNoticeCacheKey($user, $accessToken, $alert['type']),
            now(),
            now()->addHours(config('subscription.notice_cache_hours', 2))
        );
    }

    private function pluginNoticeCacheKey(User $user, AccessToken $accessToken, string $type): string
    {
        $domain = $this->domainNormalizer->normalize($accessToken->domain) ?? 'unknown';

        return "subscription_notice_{$user->id}_{$domain}_{$type}";
    }
}
