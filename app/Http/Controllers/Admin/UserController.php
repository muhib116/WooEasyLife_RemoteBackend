<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackageUseHistory;
use App\Models\SmsBalance;
use App\Models\SmsRecharge;
use App\Models\User;
use App\Models\UserPackage;
use App\Traits\Transaction;
use App\Traits\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class UserController extends Controller
{
    use Transaction;

    public function index()
    {
        $users = User::query()
            ->where('role', 'user')
            ->withSum(['userPackage as remaining_order' => function ($query) {
                $query->where('is_active', 1);
            }], 'remaining_order')
            ->orderBy('id', 'desc')
            ->get();
        // return $users;
        return Inertia::render('Users/Index', compact('users'));
    }

    public $notices = [
        ['type' => 'success', 'message' => 'You did an amazing job!'],
        ['type' => 'warning', 'message' => 'Be careful! Something might go wrong.'],
        ['type' => 'danger', 'message' => 'Oops! Something went wrong.'],
        ['type' => 'info', 'message' => 'Just so you know, this is important.'],
        ['type' => 'success', 'message' => 'Well done! Keep up the good work.'],
        ['type' => 'warning', 'message' => 'Watch out! You might need to check this.'],
        ['type' => 'danger', 'message' => 'Alert! Action is required immediately.'],
        ['type' => 'info', 'message' => 'Heads up! Here’s some useful information.'],
        ['type' => 'success', 'message' => 'Great work! Everything is running smoothly.'],
        ['type' => 'warning', 'message' => 'This needs your attention before proceeding.'],
        ['type' => 'danger', 'message' => 'Error detected! Fix it as soon as possible.'],
        ['type' => 'info', 'message' => 'Here’s a quick update on your progress.'],
        ['type' => 'success', 'message' => 'You nailed it! Fantastic result.'],
        ['type' => 'warning', 'message' => 'This might not work as expected.'],
        ['type' => 'danger', 'message' => 'Caution! Something is broken.'],
        ['type' => 'info', 'message' => 'Did you know? This could be useful for you.'],
        ['type' => 'success', 'message' => 'Perfect! Everything is on track.'],
        ['type' => 'warning', 'message' => 'A minor issue was detected, please check.'],
        ['type' => 'danger', 'message' => 'System failure! Take immediate action.'],
        ['type' => 'info', 'message' => 'FYI: A new update is available.']
    ];

    public function getUser(Request $request)
    {
        // return dns_get_record('localhost', DNS_A);
        // 104.18.32.47
        // 172.64.155.209
        // return $this->getDomainFromUrl('localhost');
        // LogHelper::saveLog('hi', 'hi');
        // echo "Called from line: " . $backtrace[0]['line'] . PHP_EOL;
        $token = $request->bearerToken();
        $accessToken = AccessToken::findToken($token);
        if (!$accessToken) {
            return $this->errorResponse('Invalid Token', 401);
        }

        try {
            $user = $accessToken->tokenable;
            // $types = ['success', 'warning', 'danger', 'info', null];

            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401);
            }

            $notice = null;
            $frontendDomain = $this->getRequestDomain();

            $smsQuery = SmsBalance::query()
                ->where('user_id', $user->id)
                ->where('domain', $this->getDomainFromUrl($accessToken->domain));
            $smsBalance = $smsQuery->sum('amount');
            $smsCount = $smsQuery->where('type', 'out')->count();

            // Cache key for tracking last notice time
            $smsNoticeCacheKey = 'sms_balance_notice_' . $user->id;

            if ($smsBalance <= 20 && $smsCount > 0) {
                // Check if the notice was shown in the last 2 hours
                if (!Cache::has($smsNoticeCacheKey)) {
                    $notice = [
                        ['type' => 'info', 'message' => 'Your SMS balance is less than 20TK.']
                    ];

                    // Store a timestamp in cache for 2 hours (120 minutes)
                    Cache::put($smsNoticeCacheKey, now(), now()->addHours(2));
                }
            }

            // return $accessToken->domain;
            $userPackage = UserPackage::where('user_id', $accessToken->tokenable_id)
                ->where('domain', 'LIKE', '%' . $this->getDomainFromUrl($accessToken->domain) . '%')
                ->where('is_active', true)
                ->get();
            // return $userPackage;
            $remainingOrders = collect($userPackage)->filter(function ($item) use ($frontendDomain) {
                return $this->getDomainFromUrl($item->domain) == $frontendDomain;
            })->sum('remaining_order');
            $user->remaining_order = $remainingOrders + 0;
            $user->notice = $notice; // $types[array_rand($types)] ? $notices[array_rand($notices)] : null;
            $user->sms_balance = number_format($smsBalance, 2) + 0; // $types[array_rand($types)] ? $notices[array_rand($notices)] : null;

            return response()->json($user, 200);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Get user catch', $th->getMessage());
            return $this->errorResponse('Token not found', 401);
        }
    }

    public function view($userId)
    {
        $user = User::find($userId);

        $package = UserPackage::where('user_id', $userId);
        $report = [
            'active_api_key' => AccessToken::where('tokenable_id', $userId)->count(),
            'sms_balance' => SmsBalance::where('user_id', $userId)->sum('amount'),
            'active_package' => $package->where('is_active', 1)->count(),
            'remaining_orders' => $package->where('is_active', 1)->sum('remaining_order'),
        ];
        // LogHelper::saveLog('ip', request()->ip());
        return Inertia::render('Users/View', compact('user', 'report'));
    }

    public function store(Request $request)
    {
        $roles = [
            'name' => 'required',
            'phone' => 'required',
        ];

        if (!$request->id) {
            $roles['password'] = 'required';
        }

        $request->validate($roles);

        $data = array_merge($request->only([
            'name',
            'phone',
            'email',
            'address',
            'facebook_page_link',
            'status'
        ]), [
            'role' => 'user',
            'whatsapp_phone' => $request->whatsapp_phone ?? $request->phone,
        ]);
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }
        if ($request->id) {
            $user = User::findOrFail($request->id);
            $user->update($data);
        } else {
            User::create($data);
        }
        return back()->with('success', 'User Saved Successfully!');
    }

    public function apiKeys($userId)
    {
        $user = User::findOrFail($userId);
        $tokens = AccessToken::where('tokenable_id', $user->id)->get();
        $tokens = $tokens->map(function ($token) {
            return [
                ...$token->toArray(),
                'bearer_token' => $this->decodeToken($token->access_key),
                'last_used_ago' => optional($token->last_used_at)->diffForHumans()
            ];
        });

        $packages = UserPackage::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('domain');
        $user_packages = [];
        foreach (collect($packages) as $v) {
            $user_packages[] = $v;
        }
        return Inertia::render('Users/ApiKeys', compact('user', 'tokens', 'user_packages'));
    }

    public function packages($userId)
    {
        $user = User::find($userId);
        $packages = PackageHub::where('is_active', true)->orderBy('id', 'desc')->get();
        $user_packages = UserPackage::where([
            'user_id' => $userId,
        ])->orderBy('created_at', 'desc')->get();
        return Inertia::render('Users/Packages', compact('user', 'packages', 'user_packages'));
    }

    public function useDetails($userId, $packageId)
    {
        $userPackage = UserPackage::find($packageId);
        $history = PackageUseHistory::where([
            'user_id' => $userId,
            'user_package_id' => $packageId
        ])
            ->orderBy('id', 'desc')
            ->get();

        $modifiedHistory = collect($history)->map(function ($record) {
            // return $record->use_details;
            $useDetails = collect($record->use_details);
            $record->use_details = $useDetails->map(function ($item) {
                try {
                    if (is_string($item['cart_contents']) && @unserialize($item['cart_contents']) !== false) {
                        $item['cart_contents'] = unserialize($item['cart_contents']);
                    }
                } catch (\Throwable $th) {
                }
                return $item;
            });

            return $record;
        });

        return response()->json($modifiedHistory);
    }

    public function smsRecharge($userId)
    {
        $user = User::find($userId);
        $recharge = SmsRecharge::where('user_id', $userId)->orderBy('id', 'desc')->get();
        return Inertia::render('Users/SmsRecharge', compact('user', 'recharge'));
    }

    public function smsUseHistory($userId)
    {
        $sms_history = SmsBalance::where('user_id', $userId)
            ->where('type', 'out')
            ->orderBy('id', 'desc')
            ->get();
        $user = User::find($userId);
        return Inertia::render('Users/SmsHistory', compact('user', 'sms_history'));
    }

    public function approveSmsRecharge($sms_id)
    {

        DB::beginTransaction();
        try {
            $recharge = SmsRecharge::find($sms_id);
            $recharge->update([
                'updated_by' => Auth::id(),
                'status' => 'approved',
            ]);
            $data = [
                'created_by' => Auth::id(),
                'user_id' => $recharge->user_id,
                'type' => 'in',
                'amount' => $recharge->total_amount - $recharge->transaction_charge,
                'note' => 'Recharge',
                'domain' => $recharge->domain,
            ];
            $smsBalance = SmsBalance::create($data);
            $smsBalance->transactionHistory()->create([
                'user_id' => Auth::id(),
                'created_by' => Auth::id(),
                'amount' => - ($data['amount'] + 0),
                'type' => 'out',
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        return back()->with('success', 'Recharge approved successfully');
    }
    public function rejectSmsRecharge($sms_id)
    {
        $recharge = SmsRecharge::find($sms_id);
        $recharge->update([
            'status' => 'cancelled',
        ]);
        return back()->with('success', 'Recharge reject successfully');
    }

    public function updatePurchasePackage(Request $request, $id)
    {
        $userPackage = UserPackage::find($request->id);
        if ($userPackage) {
            $userPackage->update([
                'updated_by' => Auth::id(),
                'note' => $request->note,
                'domain' => $request->domain,
            ]);
        }

        return back()->with('success', 'Package updated successfully');
    }

    public function purchase(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'limit' => 'required|integer',
            'domain' => 'required',
            'package_id' => 'required',
            'transaction_method' => 'required',
        ]);

        $package = PackageHub::find($request->package_id);

        $domain = $this->getDomainFromUrl($request->domain);
        $data = [
            'title' => $package->title,
            'description' => $package->description,
            'domain' => $domain,
            'user_id' => $user->id,
            'package_hub_id' => $package->id,
            'total_order_can_handle' => $request->limit,
            'remaining_order' => $request->limit,
            'total_order_handled' => 0,
            'per_order_rate' => $package->per_order_rate,
            'transaction_method' => $package->transaction_method,
            'transaction_number' => $package->transaction_number,
            'transaction_id' => $package->transaction_id,
            'total_cost' => $package->per_order_rate * $request->limit,
            'transaction_charge' => $request->transaction_charge,
            'is_active' => true,
            'note' => $request->note,
            'created_by' => Auth::id(),
            // 'updated_by' => Auth::id(),
        ];

        UserPackage::create($data);

        return back()->with('success', 'Package created successfully');
    }
}
