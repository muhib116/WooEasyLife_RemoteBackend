<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Http\Request;
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
            'package_purchase' => $this->packagePurchaseInfo()
        ];

        return Inertia::render('Dashboard/Dashboard', compact('data'));
    }

    private function packagePurchaseInfo()
    {
        $adminUserIds = User::where('role', 'admin')->pluck('id');
        $query = UserPackage::query()->whereNotIn('user_id', $adminUserIds);

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
}
