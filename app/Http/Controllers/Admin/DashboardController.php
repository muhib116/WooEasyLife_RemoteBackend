<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\CourierForwardRetry;
use App\Models\CourierWebhookEvent;
use App\Models\CustomerNotice;
use App\Models\PackagePaymentRequest;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\SubscriptionAlertService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

function getBoxData($title, $value, $modifier = null, $modifier_position = 'right', ...$others)
{
    $data = [
        'title' => $title,
        'value' => $value,
        // 'modifier' => '%',
        'modifier_position' => 'right',
        'modifier_style' => ''
    ];
    if ($modifier) {
        $data['modifier'] = $modifier;
    }
    if ($modifier_position) {
        $data['modifier_position'] = $modifier_position;
    }

    if (!empty($others)) {
        $data = [
            ...$data,
            ...$others
        ];
    }

    return $data;
}

class DashboardController extends Controller
{
    private const ADMIN_TEST_SOURCE = 'admin_test';

    public function index()
    {
        $tokenData = $this->packagePurchaseInfo();

        $data = [
            'overview' => $this->getOverview($tokenData),
            'tokens' => [
                'col_span' => 2,
                'title' => 'Token & Revenue',
                'link' => route('tokenLedger'),
                'link_text' => 'Open Token Ledger',
                'data' => $tokenData,
            ],
            'sms' => $this->getSmsInfo(),
            'expired_tokens' => $this->getExpiredTokenInfo(),
            'subscription_alerts' => $this->getSubscriptionAlertInfo(),
            'webhooks' => $this->getWebhookInfo(),
            'customer_notices' => $this->getCustomerNoticeInfo(),
            'payment_requests' => $this->getPaymentRequestInfo(),
        ];

        return Inertia::render('Dashboard/Dashboard', compact('data'));
    }

    /**
     * @return array<int, int|string>
     */
    private function excludedUserIds(): array
    {
        $adminUserIds = User::where('role', 'admin')->pluck('id');
        $testUsers = User::where('is_test', 1)->pluck('id');

        return [...$adminUserIds, ...$testUsers];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokenData
     * @return array<string, mixed>
     */
    private function getOverview(array $tokenData): array
    {
        $userQuery = User::query()->where('role', 'user');
        $totalMerchants = $userQuery->count();

        $currentMonthMerchants = (clone $userQuery)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $previousMonthMerchants = (clone $userQuery)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        if ($previousMonthMerchants > 0) {
            $growthPct = (($currentMonthMerchants - $previousMonthMerchants) / $previousMonthMerchants) * 100;
        } else {
            $growthPct = $currentMonthMerchants > 0 ? 100 : 0;
        }

        $tokenSell = $this->statValue($tokenData, 'Token Sell');
        $tokenUsed = $this->statValue($tokenData, 'Token Used');
        $tokenUsagePercent = $tokenSell > 0
            ? min(100, (int) round(($tokenUsed / $tokenSell) * 100))
            : 0;

        $packageQuery = UserPackage::query()
            ->whereNotIn('user_id', $this->excludedUserIds())
            ->where('is_active', true);

        $activeSubscriptions = (clone $packageQuery)->count();
        $expiringSubscriptions = (clone $packageQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        $pendingPaymentsQuery = PackagePaymentRequest::query()->where('status', 'pending');
        $pendingPayments = (clone $pendingPaymentsQuery)->count();
        $pendingPaymentsAmount = (clone $pendingPaymentsQuery)->sum('total_amount');

        return [
            'merchants_total' => $totalMerchants,
            'merchants_new_month' => $currentMonthMerchants,
            'merchants_growth_pct' => number_format($growthPct, 2),
            'merchants_growth_positive' => $growthPct >= 0,
            'pending_payments' => $pendingPayments,
            'pending_payments_amount' => number_format((float) $pendingPaymentsAmount, 2),
            'platform_revenue' => $this->statFormatted($tokenData, 'Token Sell Price'),
            'token_usage_percent' => $tokenUsagePercent,
            'token_remaining' => $this->statFormatted($tokenData, 'Token Remaining'),
            'active_subscriptions' => $activeSubscriptions,
            'expiring_subscriptions' => $expiringSubscriptions,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     */
    private function statValue(array $stats, string $title): float
    {
        foreach ($stats as $stat) {
            if (($stat['title'] ?? null) === $title) {
                return (float) str_replace(',', '', (string) ($stat['value'] ?? 0));
            }
        }

        return 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     */
    private function statFormatted(array $stats, string $title): string
    {
        foreach ($stats as $stat) {
            if (($stat['title'] ?? null) === $title) {
                $value = (string) ($stat['value'] ?? '0');
                $modifier = $stat['modifier'] ?? null;

                return $modifier ? "{$value} {$modifier}" : $value;
            }
        }

        return '0';
    }

    private function packagePurchaseInfo()
    {
        $ids = $this->excludedUserIds();
        $query = UserPackage::query()->whereNotIn('user_id', $ids);

        $token_sell = (clone $query)->sum('total_order_can_handle');
        $token_used = (clone $query)->sum('total_order_handled');
        $token_remaining = $token_sell - $token_used;
        $token_sell_price = (clone $query)->sum('total_cost');

        return [
            getBoxData('Token Sell', number_format($token_sell, 2)),
            getBoxData('Token Used', number_format($token_used, 2)),
            getBoxData('Token Remaining', number_format($token_remaining, 2)),
            getBoxData('Token Sell Price', number_format($token_sell_price, 2), 'TK'),
        ];
    }

    private function getSmsInfo()
    {
        $smsQuery = SmsBalance::query();
        $totalBalance = (clone $smsQuery)->sum('amount');
        $totalSmsSent = (clone $smsQuery)->where('type', 'out')->sum('sms_count');
        $totalSmsRecharge = (clone $smsQuery)->where('type', 'in')->sum('amount');

        return [
            'total_balance' => number_format($totalBalance, 2),
            'total_sms_sent' => number_format($totalSmsSent, 2),
            'total_sms_recharge' => number_format($totalSmsRecharge, 2),
        ];
    }

    private function getExpiredTokenInfo()
    {
        $baseQuery = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereHasMorph('tokenable', [User::class], function ($query) {
                $query->where('role', 'user');
            });

        $total = (clone $baseQuery)->count();
        $expired = (clone $baseQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
        $active = (clone $baseQuery)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();
        $expiringSoon = (clone $baseQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        $recentExpired = (clone $baseQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with(['tokenable:id,name,email'])
            ->orderByDesc('expires_at')
            ->limit(10)
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'title' => $token->title ?? $token->name,
                    'domain' => $token->domain,
                    'user_name' => $token->tokenable?->name,
                    'user_email' => $token->tokenable?->email,
                    'expires_at' => $token->expires_at?->format('Y-m-d H:i'),
                    'expired_ago' => $token->expires_at?->diffForHumans(),
                    'status' => (bool) $token->status,
                ];
            })
            ->values();

        return [
            'title' => 'API Token Expiry',
            'link' => route('users.index'),
            'link_text' => 'Manage Merchants',
            'total' => $total,
            'expired' => $expired,
            'active' => $active,
            'expiring_soon' => $expiringSoon,
            'recent' => $recentExpired,
            'summary' => [
                getBoxData('Total API Tokens', $total),
                getBoxData('Expired Tokens', $expired),
                getBoxData('Expiring Soon', $expiringSoon),
                getBoxData('Active Tokens', $active),
            ],
        ];
    }

    private function getSubscriptionAlertInfo()
    {
        $feed = app(SubscriptionAlertService::class)->adminAlertFeed(12);
        $summary = app(SubscriptionAlertService::class)->summarizeAdminFeed(
            app(SubscriptionAlertService::class)->adminAlertFeed(500)
        );

        return [
            'title' => 'Subscription Alerts',
            'link' => Route::has('subscriptionAlerts.index') ? route('subscriptionAlerts.index') : url('/subscription-alerts'),
            'link_text' => 'View All Alerts',
            'summary' => $summary,
            'recent' => $feed->map(function (array $alert) {
                return [
                    'type' => $alert['type'],
                    'severity' => $alert['severity'],
                    'message' => $alert['message'],
                    'user_id' => $alert['user_id'],
                    'user_name' => $alert['user_name'],
                    'domain' => $alert['domain'],
                ];
            })->values(),
        ];
    }

    private function getWebhookInfo()
    {
        $eventsQuery = $this->courierWebhookEventsQuery();

        $totalEvents = (clone $eventsQuery)->count();
        $successCount = (clone $eventsQuery)->where('forward_status', 'success')->count();
        $failedCount = (clone $eventsQuery)->where('forward_status', 'failed')->count();
        $retryQueuedCount = (clone $eventsQuery)->where('forward_status', 'retry_queued')->count();
        $orphanCount = (clone $eventsQuery)->where('forward_status', 'orphan')->count();
        $pendingRetries = CourierForwardRetry::query()->where('status', 'pending')->count();
        $failedRetries = CourierForwardRetry::query()->where('status', 'failed')->count();

        $lastEvent = (clone $eventsQuery)->orderByDesc('id')->first();

        $successRate = $totalEvents > 0
            ? round(($successCount / $totalEvents) * 100)
            : 0;

        $recentEvents = (clone $eventsQuery)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'partner' => $event->partner,
                    'environment' => $event->environment,
                    'consignment_id' => $event->consignment_id,
                    'wc_order_id' => $event->wc_order_id,
                    'site_url' => $event->site_url,
                    'event_type' => $event->event_type,
                    'forward_status' => $event->forward_status,
                    'forward_message' => $event->forward_message,
                    'created_at' => $event->created_at?->format('Y-m-d H:i'),
                    'received_ago' => $event->created_at?->diffForHumans(),
                ];
            })
            ->values();

        $partnerBreakdown = (clone $eventsQuery)
            ->selectRaw('partner, COUNT(*) as total')
            ->groupBy('partner')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'partner' => $row->partner,
                'total' => (int) $row->total,
            ])
            ->values();

        return [
            'title' => 'Courier Webhook Activity',
            'link' => Route::has('webhooks.index') ? route('webhooks.index') : url('/webhooks'),
            'link_text' => 'View All Activities',
            'total_events' => $totalEvents,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'retry_queued_count' => $retryQueuedCount,
            'orphan_count' => $orphanCount,
            'pending_retries' => $pendingRetries,
            'failed_retries' => $failedRetries,
            'success_rate' => $successRate,
            'last_event_at' => $lastEvent?->created_at?->format('Y-m-d H:i'),
            'last_forward_status' => $lastEvent?->forward_status,
            'recent' => $recentEvents,
            'partners' => $partnerBreakdown,
        ];
    }

    private function courierWebhookEventsQuery()
    {
        return CourierWebhookEvent::query()->where(function ($builder) {
            $builder->whereNull('payload_summary')
                ->orWhere('payload_summary->source', '!=', self::ADMIN_TEST_SOURCE);
        });
    }

    private function getCustomerNoticeInfo()
    {
        $notices = CustomerNotice::query()->orderByDesc('priority')->orderByDesc('id')->get();

        $live = 0;
        $scheduled = 0;
        $inactive = 0;

        foreach ($notices as $notice) {
            $status = $this->resolveNoticeStatus($notice);

            match ($status) {
                'live' => $live++,
                'scheduled' => $scheduled++,
                default => $inactive++,
            };
        }

        $audienceLabels = [
            'all' => 'All merchants',
            'active_subscribers' => 'Active subscribers',
            'expiring_soon' => 'Expiring soon',
            'recent_expired' => 'Recently expired',
            'not_renewed' => 'Not renewed',
        ];

        $typeLabels = [
            'offer' => 'Offer',
            'maintenance' => 'Maintenance',
            'feature' => 'Feature',
            'general' => 'General',
        ];

        $recent = $notices
            ->filter(fn (CustomerNotice $notice) => in_array($this->resolveNoticeStatus($notice), ['live', 'scheduled'], true))
            ->take(5)
            ->map(function (CustomerNotice $notice) use ($audienceLabels, $typeLabels) {
                return [
                    'id' => $notice->id,
                    'title' => $notice->title,
                    'type' => $notice->type,
                    'type_label' => $typeLabels[$notice->type] ?? $notice->type,
                    'audience' => $notice->audience,
                    'audience_label' => $audienceLabels[$notice->audience] ?? $notice->audience,
                    'severity' => $notice->severity,
                    'status' => $this->resolveNoticeStatus($notice),
                ];
            })
            ->values();

        return [
            'title' => 'Customer Notices',
            'link' => Route::has('customerNotices.index') ? route('customerNotices.index') : url('/customer-notices'),
            'link_text' => 'Manage Notices',
            'summary' => [
                'total' => $notices->count(),
                'live' => $live,
                'scheduled' => $scheduled,
                'inactive' => $inactive,
            ],
            'recent' => $recent,
        ];
    }

    private function resolveNoticeStatus(CustomerNotice $notice): string
    {
        if (! $notice->is_active) {
            return 'inactive';
        }

        $now = now();

        if ($notice->starts_at && $notice->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($notice->ends_at && $notice->ends_at->isPast()) {
            return 'inactive';
        }

        return 'live';
    }

    private function getPaymentRequestInfo()
    {
        $baseQuery = PackagePaymentRequest::query();

        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $approved = (clone $baseQuery)->where('status', 'approved')->count();
        $cancelled = (clone $baseQuery)->where('status', 'cancelled')->count();
        $total = (clone $baseQuery)->count();
        $pendingAmount = (clone $baseQuery)->where('status', 'pending')->sum('total_amount');

        $recentPending = (clone $baseQuery)
            ->where('status', 'pending')
            ->with(['user:id,name', 'packageHub:id,title', 'website:id,domain'])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function (PackagePaymentRequest $payment) {
                return [
                    'id' => $payment->id,
                    'user_name' => $payment->user?->name,
                    'domain' => $payment->website?->domain ?? $payment->domain,
                    'package_title' => $payment->packageHub?->title,
                    'total_amount' => number_format((float) ($payment->total_amount ?? 0), 2),
                    'submitted_ago' => $payment->created_at?->diffForHumans(),
                ];
            })
            ->values();

        return [
            'title' => 'Payment Requests',
            'link' => Route::has('packagePayments.index') ? route('packagePayments.index') : url('/package-payments'),
            'link_text' => 'Review Payments',
            'summary' => [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'cancelled' => $cancelled,
                'pending_amount' => number_format((float) $pendingAmount, 2),
            ],
            'recent' => $recentPending,
        ];
    }
}
