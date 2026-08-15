<?php

namespace App\Services\FraudCheck;

use App\Models\AccessToken;
use App\Services\Courier\CourierAccountService;
use App\Services\WhitelistedDomainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Plugin merchants without Steadfast portal credentials (and not domain-whitelisted)
 * get a landing-style free courier-history quota, then an upgrade alert.
 */
class PluginFraudCheckFreeQuota
{
    public function __construct(
        private MerchantSteadfastFraudCredentialResolver $steadfastCredentialResolver,
        private WhitelistedDomainService $whitelistedDomainService,
        private CourierAccountService $courierAccountService,
    ) {}

    public function limit(): int
    {
        return max(1, (int) config('fraud_check.plugin_free_checks_without_steadfast', 10));
    }

    public function isUnlimited(Request $request): bool
    {
        if ($this->steadfastCredentialResolver->hasCredentialsForRequest($request)) {
            return true;
        }

        return $this->whitelistedDomainService->isRequestAllowed();
    }

    public function isOnFreeTier(Request $request): bool
    {
        // Admin web fraud checks have no plugin access token — do not gate them.
        if ($this->courierAccountService->resolveAccessToken($request) === null) {
            return false;
        }

        return ! $this->isUnlimited($request);
    }

    public function usageKey(Request $request): ?string
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        if (! $accessToken instanceof AccessToken) {
            return null;
        }

        return 'plugin_fraud_check:free:'.$accessToken->id.':'.now()->toDateString();
    }

    public function used(Request $request): int
    {
        $key = $this->usageKey($request);

        if ($key === null) {
            return 0;
        }

        return (int) Cache::get($key, 0);
    }

    public function remaining(Request $request): int
    {
        if ($this->isUnlimited($request)) {
            return PHP_INT_MAX;
        }

        return max(0, $this->limit() - $this->used($request));
    }

    public function hasRemaining(Request $request, int $needed = 1): bool
    {
        if ($this->isUnlimited($request)) {
            return true;
        }

        $needed = max(0, $needed);
        if ($needed === 0) {
            return true;
        }

        return $this->remaining($request) >= $needed;
    }

    public function consume(Request $request, int $count = 1): void
    {
        if ($this->isUnlimited($request) || $count < 1) {
            return;
        }

        $key = $this->usageKey($request);

        if ($key === null) {
            return;
        }

        if (! Cache::has($key)) {
            Cache::put($key, $count, now()->endOfDay());

            return;
        }

        Cache::increment($key, $count);
    }

    /**
     * Correct deny payload when free quota blocks the request.
     *
     * @return array<string, mixed>
     */
    public function denyPayload(Request $request, int $needed = 1): array
    {
        $remaining = $this->remaining($request);
        $needed = max(1, $needed);

        if ($remaining > 0 && $needed > $remaining) {
            return $this->batchTooLargePayload($request, $needed, $remaining);
        }

        return $this->exhaustedPayload($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function exhaustedPayload(Request $request): array
    {
        $limit = $this->limit();

        return [
            'status' => false,
            'limited' => true,
            'code' => 'steadfast_credentials_required',
            'title' => 'ফ্রি চেক শেষ',
            'title_en' => 'Free checks used up',
            'message' => "আজকের {$limit}টি ফ্রি কাস্টমার হিস্ট্রি চেক শেষ। আনলিমিটেড চেকের জন্য Courier → Steadfast-এ portal username/password যোগ করুন।",
            'message_en' => "You've used today's {$limit} free customer history checks. Add Steadfast portal username/password under Courier → Steadfast for unlimited checks.",
            'free_check_limit' => $limit,
            'used_free_checks' => $this->used($request),
            'remaining_free_checks' => 0,
            'action' => [
                'type' => 'connect_steadfast_credentials',
                'route' => 'courierTab',
                'tab' => 'steadfast',
                'label' => 'Steadfast credential যোগ করুন',
                'label_en' => 'Add Steadfast credentials',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function batchTooLargePayload(Request $request, int $needed, int $remaining): array
    {
        $limit = $this->limit();

        return [
            'status' => false,
            'limited' => true,
            'code' => 'steadfast_credentials_required',
            'reason' => 'batch_too_large',
            'title' => 'ফ্রি চেক কম আছে',
            'title_en' => 'Not enough free checks',
            'message' => "আজ আর {$remaining}টি ফ্রি চেক বাকি (লিমিট {$limit})। এখন {$needed}টি সিলেক্ট করা হয়েছে — কম অর্ডার সিলেক্ট করুন, অথবা Steadfast credential যোগ করে আনলিমিটেড নিন।",
            'message_en' => "Only {$remaining} free check(s) left today (limit {$limit}). You selected {$needed}. Select fewer orders, or add Steadfast credentials for unlimited access.",
            'free_check_limit' => $limit,
            'used_free_checks' => $this->used($request),
            'remaining_free_checks' => $remaining,
            'requested_checks' => $needed,
            'action' => [
                'type' => 'connect_steadfast_credentials',
                'route' => 'courierTab',
                'tab' => 'steadfast',
                'label' => 'Steadfast credential যোগ করুন',
                'label_en' => 'Add Steadfast credentials',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function attachFreeAccessMeta(array $report, Request $request): array
    {
        if (! $this->isOnFreeTier($request)) {
            return $report;
        }

        $remaining = $this->remaining($request);
        $limit = $this->limit();
        $used = $this->used($request);
        $low = $remaining > 0 && $remaining <= 3;

        $report['free_access'] = [
            'active' => true,
            'free_check_limit' => $limit,
            'used_free_checks' => $used,
            'remaining_free_checks' => $remaining,
            'low' => $low,
            'notice' => $remaining > 0
                ? ($low
                    ? "ফ্রি চেক প্রায় শেষ — বাকি {$remaining}/{$limit}। আনলিমিটেডের জন্য Steadfast credential যোগ করুন।"
                    : "ফ্রি চেক বাকি {$remaining}/{$limit}। আনলিমিটেড অ্যাক্সেসের জন্য Steadfast credential যোগ করুন।")
                : 'ফ্রি চেক শেষ। Steadfast credential যোগ করে ফুল অ্যাক্সেস নিন।',
            'notice_en' => $remaining > 0
                ? ($low
                    ? "Almost out of free checks — {$remaining}/{$limit} left. Add Steadfast credentials for unlimited access."
                    : "{$remaining}/{$limit} free checks left today. Add Steadfast credentials for unlimited access.")
                : 'Free checks used up. Add Steadfast credentials for full access.',
            'action' => [
                'type' => 'connect_steadfast_credentials',
                'route' => 'courierTab',
                'tab' => 'steadfast',
                'label' => 'Steadfast credential যোগ করুন',
                'label_en' => 'Add Steadfast credentials',
            ],
        ];

        return $report;
    }

    public function countPhonesInRequest(Request $request): int
    {
        if (is_array($request->data)) {
            $count = 0;
            foreach ($request->data as $item) {
                if (is_array($item) && ! empty($item['phone'])) {
                    $count++;
                }
            }

            return $count;
        }

        $phone = $request->input('phone');

        return filled($phone) ? 1 : 0;
    }
}