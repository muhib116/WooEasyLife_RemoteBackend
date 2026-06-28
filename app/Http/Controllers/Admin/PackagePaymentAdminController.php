<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\PackagePaymentService;
use App\Services\SubscriptionAlertService;
use App\Services\WebsiteAggregatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackagePaymentAdminController extends Controller
{
    public function __construct(
        protected PackagePaymentService $packagePaymentService
    ) {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $payments = PackagePaymentRequest::query()
            ->with(['user:id,name,email,phone', 'packageHub:id,title,per_order_rate'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $counts = [
            'pending' => PackagePaymentRequest::where('status', 'pending')->count(),
            'approved' => PackagePaymentRequest::where('status', 'approved')->count(),
            'cancelled' => PackagePaymentRequest::where('status', 'cancelled')->count(),
        ];

        return Inertia::render('PackagePayments/Index', [
            'payments' => $payments,
            'counts' => $counts,
            'status' => $status,
        ]);
    }

    public function userBilling(
        $userId,
        WebsiteAggregatorService $websiteAggregator,
        SubscriptionAlertService $subscriptionAlertService
    ) {
        $user = User::findOrFail($userId);
        $user->loadCount(['websites', 'merchantEmployees']);
        $plans = PackageHub::query()->where('is_active', true)->orderBy('index')->get();

        $payments = PackagePaymentRequest::query()
            ->with('packageHub:id,title,per_order_rate')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        $domains = collect($websiteAggregator->forUser($user))
            ->pluck('domain')
            ->unique()
            ->values()
            ->all();

        $alerts = $subscriptionAlertService->collectPortalAlerts($user, $domains);
        $tab = request()->query('tab', 'payments');

        return Inertia::render('Users/Billing/Index', compact(
            'user',
            'payments',
            'plans',
            'domains',
            'packages',
            'alerts',
            'tab'
        ));
    }

    public function adminCreate(Request $request, $userId)
    {
        $request->validate([
            'package_hub_id' => 'required|integer',
            'order_limit' => 'required|integer|min:1',
            'domain' => 'required|string',
            'total_amount' => 'required|numeric|min:0.01',
            'transaction_charge' => 'nullable|numeric|min:0',
            'transaction_method' => 'required|string',
            'transaction_id' => 'nullable|string',
            'account_number' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $user = User::findOrFail($userId);

        try {
            $this->packagePaymentService->createRequest($user, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Payment request submitted successfully.');
    }

    public function approve($paymentId)
    {
        $paymentRequest = PackagePaymentRequest::findOrFail($paymentId);

        try {
            $this->packagePaymentService->approve($paymentRequest);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Payment approved and subscription updated.');
    }

    public function reject($paymentId)
    {
        $paymentRequest = PackagePaymentRequest::findOrFail($paymentId);

        try {
            $this->packagePaymentService->reject($paymentRequest);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Payment request rejected.');
    }
}
