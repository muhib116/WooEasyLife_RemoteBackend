<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\CourierForwardRetry;
use App\Models\CourierWebhookEvent;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserPackage;
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
        $data = [
            // 'user' => ,
            'users' => [
                'title' => 'Users',
                'link' => route('users.index'),
                'link_text' => 'See User List',
                'data' => $this->getUserStatistics()
            ],
            'tokens' => [
                'col_span' => 2,
                'title' => 'Tokens',
                'link' => route('tokenLedger'),
                'link_text' => 'See Detail Token Ledger',
                'data' => $this->packagePurchaseInfo()
            ],
            'sms' => $this->getSmsInfo(),
            'package_purchase' => $this->packagePurchaseInfo(),
            'expired_tokens' => $this->getExpiredTokenInfo(),
            'webhooks' => $this->getWebhookInfo(),
        ];

        return Inertia::render('Dashboard/Dashboard', compact('data'));
    }

    private function packagePurchaseInfo()
    {
        $adminUserIds = User::where('role', 'admin')->pluck('id');
        $testUsers = User::where('is_test', 1)->pluck('id');
        $adminUserIds = User::where('role', 'admin')->pluck('id');
        $testUsers = User::where('is_test', 1)->pluck('id');
        $ids = [...($adminUserIds ?? []), ...($testUsers ?? [])];
        $query = UserPackage::query()->whereNotIn('user_id', $ids);

        $token_sell = (clone $query)->sum('total_order_can_handle');
        $token_used = (clone $query)->sum('total_order_handled');
        $token_remaining = $token_sell - $token_used;
        $token_sell_price = (clone $query)->sum('total_cost');
        $transaction_charge = (clone $query)->sum('transaction_charge');

        return [
            getBoxData('Token Sell', number_format($token_sell, 2)),
            getBoxData('Token Used', number_format($token_used, 2)),
            getBoxData('Token Remaining', number_format($token_remaining, 2)),
            getBoxData('Token Sell Price', number_format($token_sell_price, 2), 'TK'),
            getBoxData('Transaction Charge', number_format($transaction_charge, 2), 'TK'),
        ];
    }

    private function getUserStatistics()
    {
        // Base user query to filter users with role 'user'
        $userQuery = User::query()->where('role', 'user');

        // Get total user count
        $totalUser = $userQuery->count();

        // Clone the query to avoid modifying the base query
        $currentMonthUsers = (clone $userQuery)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $previousMonthUsers = (clone $userQuery)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        if ($previousMonthUsers > 0) {
            $percentageChange = (($currentMonthUsers - $previousMonthUsers) / $previousMonthUsers) * 100;
        } else {
            $percentageChange = $currentMonthUsers > 0 ? 100 : 0; // If there were no users last month, assume a 100% increase
        }


        return [
            getBoxData('Total User', $totalUser),
            getBoxData('New User Of This Month', $currentMonthUsers),
            getBoxData('Prev Month User', $previousMonthUsers),
            getBoxData('Increase / Decrease', number_format($percentageChange, 2), '%'),
            // 'total_user' => $totalUser,
            // 'this_month_user' => $currentMonthUsers,
            // 'previous_month_user' => $previousMonthUsers,
            // 'percent_increase_from_previous_month' => $percentageChange
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
            'link' => route('apiKeys.index'),
            'link_text' => 'Manage API Keys',
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
}
