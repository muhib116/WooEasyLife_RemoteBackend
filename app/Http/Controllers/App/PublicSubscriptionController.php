<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PackageHub;
use App\Services\MerchantPortalContext;
use App\Services\PublicSubscriptionService;
use App\Services\RbacService;
use Illuminate\Http\Request;

class PublicSubscriptionController extends Controller
{
    public function store(
        Request $request,
        PublicSubscriptionService $subscriptionService,
        MerchantPortalContext $portalContext,
        RbacService $rbac,
    ) {
        $packageHub = PackageHub::query()
            ->where('id', $request->integer('package_hub_id'))
            ->where('is_active', true)
            ->first();

        $isFreeTrial = $packageHub?->package_duration === 'free_trial';

        $validated = $request->validate([
            'package_hub_id' => 'required|integer',
            'website_url' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|string|max:30',
            'whatsapp_number' => 'required|string|max:30',
            'address' => 'required|string|max:1000',
            'order_limit' => 'nullable|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
            'transaction_charge' => 'nullable|numeric|min:0',
            'transaction_method' => ($isFreeTrial ? 'nullable' : 'required').'|string|max:50',
            'transaction_id' => ($isFreeTrial ? 'nullable' : 'required').'|string|max:100',
            'account_number' => ($isFreeTrial ? 'nullable' : 'required').'|string|max:50',
            'note' => 'nullable|string|max:2000',
        ], [
            'website_url.required' => 'ওয়েবসাইট URL বা ডোমেইন লিখুন।',
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'email.email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
            'contact_number.required' => 'যোগাযোগের মোবাইল নম্বর লিখুন।',
            'whatsapp_number.required' => 'WhatsApp নম্বর লিখুন।',
            'address.required' => 'ঠিকানা লিখুন।',
            'transaction_method.required' => 'পেমেন্ট পদ্ধতি বেছে নিন।',
            'transaction_id.required' => 'Transaction ID লিখুন।',
            'account_number.required' => 'যে নম্বর থেকে পাঠিয়েছেন সেটি লিখুন।',
        ]);

        if ($isFreeTrial) {
            $validated['transaction_method'] ??= 'Free Trial';
            $validated['transaction_id'] ??= 'N/A';
            $validated['account_number'] ??= $validated['contact_number'];
            $validated['total_amount'] = 0;
        }

        $user = $request->user();
        $canUsePortalPayment = false;

        if ($user && $portalContext->canAccessPortal($user) && ! $isFreeTrial) {
            $canUsePortalPayment = $rbac->hasPermission($user, 'billing.manage');
        }

        try {
            $result = $subscriptionService->submit($user, $validated, $canUsePortalPayment);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('subscription_submitted', [
            'inquiry_id' => $result['inquiry']->id,
            'plan_title' => $result['inquiry']->packageHub?->title,
            'payment_request_id' => $result['payment_request_id'],
        ]);
    }
}
