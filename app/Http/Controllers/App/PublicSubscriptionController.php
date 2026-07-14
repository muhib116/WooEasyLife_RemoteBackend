<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PackageHub;
use App\Services\MerchantPortalContext;
use App\Services\PublicSubscriptionService;
use App\Services\RbacService;
use App\Services\SubscriptionPaymentConfigService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicSubscriptionController extends Controller
{
    public function validateFields(Request $request, PublicSubscriptionService $subscriptionService)
    {
        $data = $request->validate([
            'website_url' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
        ]);

        return response()->json(
            $subscriptionService->validateRealtime($request->user(), $data)
        );
    }

    /**
     * Soft-capture a pricing lead after the contact form is filled,
     * even when DNS / payment is incomplete.
     */
    public function saveLead(Request $request, PublicSubscriptionService $subscriptionService)
    {
        $validated = $request->validate([
            'package_hub_id' => 'required|integer',
            'website_url' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => ['required', 'string', 'max:30', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'whatsapp_number' => ['required', 'string', 'max:30', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'address' => 'nullable|string|max:1000',
            'order_limit' => 'nullable|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
            'dns_verified' => 'nullable|boolean',
        ], [
            'package_hub_id.required' => 'প্ল্যান নির্বাচন করা হয়নি।',
            'website_url.required' => 'ওয়েবসাইট URL বা ডোমেইন লিখুন।',
            'customer_name.required' => 'আপনার নাম লিখুন।',
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'email.email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
            'contact_number.required' => 'যোগাযোগের মোবাইল নম্বর লিখুন।',
            'contact_number.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর লিখুন (যেমন: 017XXXXXXXX)।',
            'whatsapp_number.required' => 'WhatsApp নম্বর লিখুন।',
            'whatsapp_number.regex' => 'সঠিক বাংলাদেশি WhatsApp নম্বর লিখুন (যেমন: 017XXXXXXXX)।',
        ]);

        try {
            $result = $subscriptionService->saveLead($request->user(), $validated);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'inquiry_id' => $result['inquiry']->id,
            'status' => $result['inquiry']->status,
        ]);
    }

    public function store(
        Request $request,
        PublicSubscriptionService $subscriptionService,
        MerchantPortalContext $portalContext,
        RbacService $rbac,
        SubscriptionPaymentConfigService $paymentConfig,
    ) {
        $packageHub = PackageHub::query()
            ->where('id', $request->integer('package_hub_id'))
            ->where('is_active', true)
            ->first();

        if (! $packageHub) {
            throw ValidationException::withMessages([
                'package_hub_id' => 'নির্বাচিত প্যাকেজটি এখন উপলব্ধ নেই।',
            ]);
        }

        $isFreeTrial = $packageHub->package_duration === 'free_trial';
        $allowedMethods = $paymentConfig->allowedTransactionMethods();

        if (! $isFreeTrial && $allowedMethods === []) {
            throw ValidationException::withMessages([
                'transaction_method' => 'পেমেন্ট পদ্ধতি এখন উপলব্ধ নেই। অনুগ্রহ করে WhatsApp সাপোর্টে যোগাযোগ করুন।',
            ]);
        }

        $validated = $request->validate([
            'package_hub_id' => 'required|integer',
            'website_url' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => ['required', 'string', 'max:30', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'whatsapp_number' => ['required', 'string', 'max:30', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'address' => 'required|string|max:1000',
            'order_limit' => 'nullable|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
            'transaction_charge' => 'nullable|numeric|min:0',
            'transaction_method' => $isFreeTrial
                ? 'nullable|string|max:50'
                : ['required', 'string', 'max:50', Rule::in($allowedMethods)],
            'transaction_id' => ($isFreeTrial ? 'nullable' : 'required').'|string|max:100',
            'account_number' => $isFreeTrial
                ? 'nullable|string|max:50'
                : ['required', 'string', 'max:50', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'note' => 'nullable|string|max:2000',
        ], [
            'package_hub_id.required' => 'প্ল্যান নির্বাচন করা হয়নি। অনুগ্রহ করে আবার চেষ্টা করুন।',
            'website_url.required' => 'ওয়েবসাইট URL বা ডোমেইন লিখুন।',
            'customer_name.required' => 'আপনার নাম লিখুন।',
            'email.required' => 'ইমেইল ঠিকানা লিখুন।',
            'email.email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
            'contact_number.required' => 'যোগাযোগের মোবাইল নম্বর লিখুন।',
            'contact_number.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর লিখুন (যেমন: 017XXXXXXXX)।',
            'whatsapp_number.required' => 'WhatsApp নম্বর লিখুন।',
            'whatsapp_number.regex' => 'সঠিক বাংলাদেশি WhatsApp নম্বর লিখুন (যেমন: 017XXXXXXXX)।',
            'address.required' => 'ঠিকানা লিখুন।',
            'transaction_method.required' => 'পেমেন্ট পদ্ধতি বেছে নিন।',
            'transaction_method.in' => 'সঠিক পেমেন্ট পদ্ধতি বেছে নিন।',
            'transaction_id.required' => 'Transaction ID লিখুন।',
            'account_number.required' => 'যে নম্বর থেকে পাঠিয়েছেন সেটি লিখুন।',
            'account_number.regex' => 'সঠিক বাংলাদেশি পাঠানোর নম্বর লিখুন (যেমন: 017XXXXXXXX)।',
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
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $request->session()->put(
            PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY,
            $result['inquiry']->id,
        );

        return back()->with('subscription_submitted', [
            'inquiry_id' => $result['inquiry']->id,
            'plan_title' => $result['inquiry']->packageHub?->title,
            'payment_request_id' => $result['payment_request_id'],
            'is_free_trial' => $isFreeTrial,
            'value' => $isFreeTrial ? 0 : (float) ($validated['total_amount'] ?? 0),
            'currency' => 'BDT',
            'pending' => $subscriptionService->serializePending($result['inquiry']),
        ]);
    }
}
