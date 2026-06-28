<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\PackageHub;
use App\Models\PackageUseHistory;
use App\Models\RouteHit;
use App\Models\SmsBalance;
use App\Models\SmsRecharge;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Services\DomainNormalizer;
use App\Services\MerchantSetupService;
use App\Services\PackagePaymentService;
use App\Services\PackagePlanResolver;
use App\Services\PlanAssignmentService;
use App\Services\SubscriptionAlertService;
use App\Services\SubscriptionPaymentConfigService;
use App\Services\LicenseProvisioningService;
use App\Services\WebsiteAggregatorService;
use App\Services\SubscriptionAdminService;
use App\Services\WebsiteRemovalService;
use App\Traits\Transaction;
use App\Traits\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    use Transaction;

    public function index()
    {
        $users = $this->usersQuery()->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'trashed' => false,
        ]);
    }

    public function trashed()
    {
        $users = $this->usersQuery(true)->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'trashed' => true,
        ]);
    }

    private function usersQuery(bool $onlyTrashed = false)
    {
        $query = $onlyTrashed ? User::onlyTrashed() : User::query();

        return $query
            ->withSum(['userPackage as remaining_order' => function ($query) {
                $query->where('is_active', 1);
            }], 'remaining_order')
            ->withCount(['websites', 'merchantEmployees'])
            ->orderBy($onlyTrashed ? 'deleted_at' : 'id', 'desc');
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

        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            return $this->errorResponse('Expired', 401);
        }

        try {
            $user = User::findForApiAccess((int) $accessToken->tokenable_id);

            if (! $user) {
                return $this->errorResponse('Unauthenticated', 401);
            }

            $domainNormalizer = app(DomainNormalizer::class);
            $alertService = app(SubscriptionAlertService::class);

            $smsBalances = SmsBalance::query()
                ->where('user_id', $user->id)
                ->get()
                ->filter(fn (SmsBalance $balance) => $domainNormalizer->matches(
                    $balance->domain,
                    $accessToken->domain
                ));
            $smsBalance = $smsBalances->sum('amount');

            $userPackage = UserPackage::where('user_id', $accessToken->tokenable_id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (UserPackage $package) => $domainNormalizer->matches(
                    $package->domain,
                    $accessToken->domain
                ));
            $remainingOrders = $userPackage->sum('remaining_order');
            $user->remaining_order = $remainingOrders + 0;
            $user->notice = $alertService->pluginNotices($user, $accessToken);
            $user->sms_balance = round($smsBalance, 2) + 0;

            $billingAlerts = collect($alertService->collectAlerts($user, $accessToken))
                ->map(fn (array $alert) => [
                    'type' => $alert['type'],
                    'severity' => $alert['severity'],
                    'message' => $alert['message'],
                ])
                ->values()
                ->all();

            $user->billing = [
                ...app(PackagePaymentService::class)->billingSnapshot($user, $accessToken),
                'alerts' => $billingAlerts,
                'payment_methods' => app(SubscriptionPaymentConfigService::class)->forApi(),
            ];
            $user->license = [
                'expires_at' => $accessToken->expires_at,
                'status' => (bool) $accessToken->status,
            ];

            return response()->json($user, 200);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Get user catch', $th->getMessage());
            return $this->errorResponse('Token not found', 401);
        }
    }

    public function view($userId)
    {
        $user = User::find($userId);
        $user?->loadCount(['websites', 'merchantEmployees']);

        $package = UserPackage::where('user_id', $userId);
        $report = [
            'active_api_key' => AccessToken::where('tokenable_id', $userId)->count(),
            'sms_balance' => SmsBalance::where('user_id', $userId)->sum('amount'),
            'active_package' => $package->where('is_active', 1)->count(),
            'remaining_orders' => $package->where('is_active', 1)->sum('remaining_order'),
        ];

        $setup = app(MerchantSetupService::class)->progress($user);

        return Inertia::render('Users/View', compact('user', 'report', 'setup'));
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
        $admin_user = Auth::user();
        if (@$admin_user->role == 'admin') {
            if ($request->is_test) {
                $data['is_test'] = $request->is_test;
            }
        }
        if ($request->id) {
            $user = User::findOrFail($request->id);
            if (@$user->role == 'admin') {
                return back()->with('error', 'Admin User cannot be update.');
            }
            $user->update($data);
        } else {
            if (@$data['role'] == 'admin') {
                return back()->with('error', 'Admin User cannot be create.');
            }
            $user = User::create($data);

            return redirect()
                ->route('users.setup', $user->id)
                ->with('success', 'User created. Complete website setup below.');
        }

        return back()->with('success', 'User Saved Successfully!');
    }

    public function destroy($userId)
    {
        $user = User::findOrFail($userId);

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->revokePlatformAccess();
        $user->update(['status' => false]);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User moved to trash.');
    }

    public function restore($userId)
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();

        if ($user->role === 'user') {
            $user->update(['status' => true]);
        }

        return redirect()->route('users.trashed')->with('success', 'User restored successfully.');
    }

    public function forceDestroy($userId)
    {
        $user = User::onlyTrashed()->findOrFail($userId);

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'You cannot permanently delete your own account.');
        }

        DB::beginTransaction();

        try {
            $this->permanentlyDeleteUser($user);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            LogHelper::saveLog('User force delete error', $th->getMessage());

            return back()->with('error', 'Failed to permanently delete user.');
        }

        return redirect()->route('users.trashed')->with('success', 'User permanently deleted.');
    }

    private function permanentlyDeleteUser(User $user): void
    {
        $user->revokePlatformAccess();

        $smsBalanceIds = SmsBalance::where('user_id', $user->id)->pluck('id');

        TransactionHistory::query()
            ->where('user_id', $user->id)
            ->orWhere(function ($query) use ($smsBalanceIds) {
                $query->where('transactional_type', SmsBalance::class)
                    ->whereIn('transactional_id', $smsBalanceIds);
            })
            ->forceDelete();

        PackageUseHistory::where('user_id', $user->id)->forceDelete();
        UserPackage::where('user_id', $user->id)->forceDelete();
        SmsBalance::where('user_id', $user->id)->delete();
        SmsRecharge::where('user_id', $user->id)->forceDelete();
        UserBusiness::where('user_id', $user->id)->delete();
        CourierConfiguration::where('user_id', $user->id)->delete();
        RouteHit::where('user_id', $user->id)->delete();

        $user->forceDelete();
    }

    public function apiKeys($userId)
    {
        $params = ['user_id' => $userId];

        if (request()->filled('domain')) {
            $params['domain'] = request('domain');
            $params['action'] = 'license';
        }

        return redirect()->route('users.websites', $params);
    }

    public function websites($userId)
    {
        $user = User::findOrFail($userId);
        $user->loadCount(['websites', 'merchantEmployees']);
        $planResolver = app(PackagePlanResolver::class);
        $activePlans = PackageHub::where('is_active', true)->orderBy('index')->orderBy('id')->get();
        $user_packages = UserPackage::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $websites = collect(app(WebsiteAggregatorService::class)->forUser($user))
            ->values()
            ->all();

        return Inertia::render('Users/Websites/Index', [
            'user' => $user,
            'websites' => $websites,
            'packages' => $planResolver->mapPlansPayload($activePlans),
            'user_packages' => $user_packages,
            'action' => request()->query('action'),
            'domain' => request()->query('domain'),
        ]);
    }

    public function packages($userId)
    {
        $params = ['user_id' => $userId];

        if (request()->filled('domain')) {
            $params['domain'] = request('domain');
            $params['action'] = 'assign';
        }

        return redirect()->route('users.websites', $params);
    }

    public function packagesOrders(Request $request, $userId)
    {
        // $pkg = UserPackage::where('user_id', $userId)->get();

        $query = PackageUseHistory::where([
            'user_id' => $userId,
        ]);

        if ($request->filled('domain')) {
            $normalizedDomain = app(DomainNormalizer::class)->normalize($request->domain);
            $packageIds = UserPackage::query()
                ->where('user_id', $userId)
                ->get()
                ->filter(fn (UserPackage $package) => app(DomainNormalizer::class)->normalize($package->domain) === $normalizedDomain)
                ->pluck('id');

            $query->whereIn('user_package_id', $packageIds);
        }

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($start_date && $end_date) {
            $query->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $query->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $query->where('created_at', '<=', $end_date);
        } else {
            $query->whereDate('created_at', now());
        }

        $history = $query->orderBy('created_at', 'desc')->get();
        $modifiedHistory = collect($history)->map(function ($record) {
            $useDetails = collect($record->use_details);
            $record->create_time = Carbon::parse($record->created_at)
                ->format('j M Y g:i a');
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
        if($start_date) {
            $start_date = Carbon::parse($start_date)
                ->format('m/d/Y h:i a');
        }
        if($end_date) {
            $end_date = Carbon::parse($end_date)
                ->format('m/d/Y h:i a');
        }
        $user = User::find($userId);
        $user?->loadCount(['websites', 'merchantEmployees']);
        $filterDomain = $request->domain;

        return Inertia::render('Users/PackageOrders', compact('modifiedHistory', 'userId', 'start_date', 'end_date', 'user', 'filterDomain'));
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
        return redirect()->route('users.sms', [
            'user_id' => $userId,
            'tab' => 'recharge',
        ]);
    }

    public function sms($userId)
    {
        $user = User::findOrFail($userId);
        $user->loadCount(['websites', 'merchantEmployees']);
        $recharge = SmsRecharge::where('user_id', $userId)->orderBy('id', 'desc')->get();
        $sms_history = SmsBalance::where('user_id', $userId)
            ->where('type', 'out')
            ->orderBy('id', 'desc')
            ->get();
        $user_packages = UserPackage::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('domain');
        $tab = request()->query('tab', 'recharge');

        return Inertia::render('Users/Sms/Index', compact(
            'user',
            'recharge',
            'sms_history',
            'user_packages',
            'tab'
        ));
    }

    public function smsUseHistory($userId)
    {
        return redirect()->route('users.sms', [
            'user_id' => $userId,
            'tab' => 'history',
        ]);
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
        $request->validate([
            'id' => 'required|integer',
            'domain' => 'nullable|string',
            'note' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'remaining_order' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
        ]);

        $userPackage = UserPackage::query()
            ->where('id', $request->id)
            ->where('user_id', $id)
            ->firstOrFail();

        $quotaLabel = ($userPackage->plan_type ?? 'legacy') === 'catalog' ? 'tokens' : 'orders';

        if ($request->filled('remaining_order')) {
            $remainingOrder = (int) $request->remaining_order;

            if ($remainingOrder > (int) $userPackage->total_order_can_handle) {
                throw ValidationException::withMessages([
                    'remaining_order' => 'Remaining '.$quotaLabel.' cannot exceed the plan quota ('
                        .$userPackage->total_order_can_handle.').',
                ]);
            }
        }

        $domain = $request->filled('domain')
            ? (app(DomainNormalizer::class)->normalize($request->domain) ?? $request->domain)
            : $userPackage->domain;

        $expiresAt = $request->has('expires_at')
            ? ($request->expires_at ?: null)
            : $userPackage->expires_at;

        $remainingOrder = $request->filled('remaining_order')
            ? (int) $request->remaining_order
            : (int) $userPackage->remaining_order;

        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : (bool) $userPackage->is_active;

        if ($isActive && $remainingOrder <= 0) {
            throw ValidationException::withMessages([
                'remaining_order' => 'Cannot activate a plan with zero remaining '.$quotaLabel.'.',
            ]);
        }

        if ($isActive && $expiresAt && now()->greaterThan($expiresAt)) {
            throw ValidationException::withMessages([
                'expires_at' => 'Cannot activate a plan that is already expired. Extend the expiry date first.',
            ]);
        }

        $userPackage->update([
            'updated_by' => Auth::id(),
            'note' => $request->input('note', $userPackage->note),
            'domain' => $domain,
            'is_active' => $isActive,
            'remaining_order' => $remainingOrder,
            'expires_at' => $expiresAt,
        ]);

        app(\App\Services\WebsiteSyncService::class)->linkUserPackage($userPackage->fresh());

        return back()->with('success', 'Subscription adjustments saved.');
    }

    public function renewSubscription(Request $request, $id, SubscriptionAdminService $subscriptionAdmin)
    {
        $request->validate([
            'user_package_id' => 'required|integer',
        ]);

        User::findOrFail($id);

        $userPackage = UserPackage::query()
            ->where('id', $request->user_package_id)
            ->where('user_id', $id)
            ->firstOrFail();

        try {
            $subscriptionAdmin->renewCatalog($userPackage);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Subscription renewed. Tokens and expiry were reset for a new plan period.');
    }

    public function changeSubscription(Request $request, $id, SubscriptionAdminService $subscriptionAdmin)
    {
        $user = User::findOrFail($id);
        $newHub = PackageHub::findOrFail($request->package_id);
        $planResolver = app(PackagePlanResolver::class);

        $rules = [
            'user_package_id' => 'required|integer',
            'package_id' => 'required|integer',
            'domain' => 'required|string',
            'transaction_method' => 'required|string',
        ];

        if ($planResolver->isLegacy($newHub)) {
            $rules['limit'] = 'required|integer|min:1';
        }

        $request->validate($rules);

        $existing = UserPackage::query()
            ->where('id', $request->user_package_id)
            ->where('user_id', $id)
            ->firstOrFail();

        try {
            $subscriptionAdmin->changePlan($user, $existing, $newHub, $request->only([
                'domain',
                'limit',
                'transaction_method',
                'transaction_number',
                'transaction_id',
                'transaction_charge',
                'note',
            ]));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Subscription plan changed successfully.');
    }

    public function destroyWebsite(Request $request, $id, WebsiteRemovalService $websiteRemoval)
    {
        $request->validate([
            'domain' => 'required|string',
        ]);

        $user = User::findOrFail($id);

        try {
            $stats = $websiteRemoval->remove($user, $request->domain);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $message = 'Website removed successfully.';
        if ($stats['packages_removed'] > 0 || $stats['licenses_removed'] > 0) {
            $message .= sprintf(
                ' Removed %d plan(s) and %d license key(s).',
                $stats['packages_removed'],
                $stats['licenses_removed']
            );
        }

        return back()->with('success', $message);
    }

    public function purchase(Request $request, $id, PlanAssignmentService $planAssignment)
    {
        $user = User::findOrFail($id);
        $package = PackageHub::findOrFail($request->package_id);
        $planResolver = app(PackagePlanResolver::class);

        $rules = [
            'domain' => 'required',
            'package_id' => 'required',
            'transaction_method' => 'required',
        ];

        if ($planResolver->isLegacy($package)) {
            $rules['limit'] = 'required|integer|min:1';
        }

        $request->validate($rules);

        try {
            $userPackage = $planAssignment->assign($user, $package, $request->only([
                'domain',
                'limit',
                'transaction_method',
                'transaction_number',
                'transaction_id',
                'transaction_charge',
                'note',
            ]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($request->boolean('redirect_to_setup')) {
            return redirect()
                ->route('users.setup', [
                    'user_id' => $user->id,
                    'step' => 'license',
                    'domain' => $userPackage->domain,
                ])
                ->with('success', 'Plan assigned. Generate the license key next.');
        }

        return back()->with('success', 'Package created successfully');
    }

    public function setup($userId)
    {
        $user = User::findOrFail($userId);
        $user->loadCount(['websites', 'merchantEmployees']);
        $setup = app(MerchantSetupService::class)->progress($user);
        $planResolver = app(PackagePlanResolver::class);
        $activePlans = PackageHub::where('is_active', true)->orderBy('index')->orderBy('id')->get();
        $defaultPackage = $activePlans->first(fn (PackageHub $plan) => $planResolver->isLegacy($plan))
            ?? $activePlans->first();
        $userPackages = UserPackage::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Users/Setup/Wizard', [
            'user' => $user,
            'setup' => $setup,
            'packages' => $planResolver->mapPlansPayload($activePlans),
            'user_packages' => $userPackages,
            'default_package_id' => $defaultPackage?->id,
            'license_token' => session('license_token'),
            'step' => request()->query('step'),
            'domain' => request()->query('domain'),
        ]);
    }

    public function validateSetupDomain(Request $request, $userId, DomainNormalizer $domainNormalizer)
    {
        $request->validate([
            'domain' => 'required|string',
        ]);

        $domain = $domainNormalizer->normalize($request->domain);
        if (! $domain) {
            return response()->json([
                'valid' => false,
                'message' => 'Enter a valid website domain (e.g. shop.example.com).',
            ], 422);
        }

        if (! $domainNormalizer->hasDnsARecord($domain)) {
            if (app()->environment('local') && in_array($domain, ['localhost', '127.0.0.1'], true)) {
                // Allow local WordPress development hostnames without public DNS.
            } else {
                return response()->json([
                    'valid' => false,
                    'message' => 'Domain must resolve to a DNS A record before continuing.',
                ], 422);
            }
        }

        return response()->json([
            'valid' => true,
            'domain' => $domain,
            'display_url' => 'https://' . $domain,
        ]);
    }

    public function setupGenerateLicense(Request $request, $userId, LicenseProvisioningService $licenseProvisioning)
    {
        $user = User::findOrFail($userId);

        $request->validate([
            'domain' => 'required|string',
        ]);

        try {
            $result = $licenseProvisioning->create(
                $user,
                $request->domain,
                [
                    'title' => $request->title,
                    'expires_at' => $request->expires_at,
                    'user_package_id' => $request->user_package_id,
                    'status' => $request->boolean('status', true),
                ],
                requireUserPackage: true,
                requireDns: true
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }

        return redirect()
            ->route('users.setup', [
                'user_id' => $userId,
                'step' => 'complete',
            ])
            ->with('license_token', $result['plain_text_token']);
    }
}
