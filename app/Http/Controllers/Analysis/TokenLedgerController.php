<?php

namespace App\Http\Controllers\Analysis;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TokenLedgerController extends Controller
{
    public function tokenLedger()
    {
        return Inertia::render('Dashboard/TokenLedger');
    }

    public function getTokenLedger(Request $request)
    {
        // Default date range (last 30 days)
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        // $query = UserPackage::query();
        $adminUserIds = User::where('role', 'admin')->pluck('id');
        $query = UserPackage::query()->whereNotIn('user_id', $adminUserIds);

        // Get Opening Balance (sum of all transactions before start_date)
        $openingBalance = (clone $query)->where('created_at', '<', $startDate)
            ->sum('total_cost');

        // Fetch Transactions within the selected date range
        $transactionsQuery = (clone $query)->with('user:id,name,role,email,phone')->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(fn($transaction) => Carbon::parse($transaction->created_at)->format('Y-M-d'));

        // Initialize running balance
        $runningBalance = $openingBalance;
        $formattedTransactions = [];
        // Process transactions date-wise
        foreach ($transactionsQuery as $date => $dailyTransactions) {
            $dailyTotal = $dailyTransactions->sum('total_cost') + $dailyTransactions->sum('transaction_charge');
            $closingBalance = $runningBalance + $dailyTotal;

            $formattedTransactions[] = [
                'date' => $date,
                'opening_balance' => $runningBalance,
                'total_transaction_amount' => $dailyTotal,
                'closing_balance' => $closingBalance,
                'total_token' => (clone $dailyTransactions)->sum('total_order_can_handle'),
                'transaction_length' => (clone $dailyTransactions)->count(),
                'transactions' => $dailyTransactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'user' => $transaction->user,
                        'title' => $transaction->title,
                        'total_cost' => $transaction->total_cost,
                        'total_order_can_handle' => $transaction->total_order_can_handle,
                        'per_order_rate' => $transaction->per_order_rate,
                        'transaction_charge' => $transaction->transaction_charge,
                        'total_order_handled' => $transaction->total_order_handled,
                        'transaction_method' => $transaction->transaction_method ?? 'Cash',
                    ];
                })->values(),
            ];

            // Update running balance for the next day's transactions
            $runningBalance = $closingBalance;
        }

        return response()->json([
            'initial_opening_balance' => $openingBalance,
            'transactions' => $formattedTransactions,
            'final_closing_balance' => $runningBalance,
        ]);
    }
}
