<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\SubscriptionInquiry;
use App\Services\LandingOrderConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderAdminController extends Controller
{
    public function __construct(
        private LandingOrderConversionService $conversionService,
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        $orders = SubscriptionInquiry::query()
            ->with([
                'packageHub:id,title,package_price',
                'user:id,name,email,phone',
                'packagePaymentRequest:id,status',
            ])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function ($inner) use ($term) {
                    $inner->where('email', 'like', $term)
                        ->orWhere('domain', 'like', $term)
                        ->orWhere('transaction_id', 'like', $term)
                        ->orWhere('contact_number', 'like', $term)
                        ->orWhere('whatsapp_number', 'like', $term)
                        ->orWhere('account_number', 'like', $term)
                        ->orWhere('customer_name', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $counts = [
            'draft' => SubscriptionInquiry::where('status', SubscriptionInquiry::STATUS_DRAFT)->count(),
            'pending' => SubscriptionInquiry::where('status', 'pending')->count(),
            'contacted' => SubscriptionInquiry::where('status', 'contacted')->count(),
            'converted' => SubscriptionInquiry::where('status', 'converted')->count(),
            'rejected' => SubscriptionInquiry::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'counts' => $counts,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function updateStatus(Request $request, SubscriptionInquiry $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,pending,contacted,converted,rejected',
        ]);

        if ($validated['status'] === 'converted') {
            return back()->with(
                'error',
                'Use “Convert to merchant” to provision the account. Status-only convert is disabled.',
            );
        }

        // Draft leads become a pending order when sales starts payment follow-up,
        // or contacted/rejected for CRM flow.
        if (
            $order->status === SubscriptionInquiry::STATUS_DRAFT
            && $validated['status'] === SubscriptionInquiry::STATUS_PENDING
        ) {
            $order->update([
                'status' => SubscriptionInquiry::STATUS_PENDING,
                'source' => $order->source === 'landing_pricing_lead'
                    ? 'landing_pricing'
                    : $order->source,
            ]);

            return back()->with('success', 'Lead promoted to pending order.');
        }

        $order->update(['status' => $validated['status']]);

        return back()->with('success', 'Order status updated.');
    }

    public function convertPreview(SubscriptionInquiry $order)
    {
        return response()->json($this->conversionService->preview($order));
    }

    public function convert(SubscriptionInquiry $order)
    {
        try {
            $result = $this->conversionService->convert($order);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $th) {
            return back()->with('error', 'Conversion failed: '.$th->getMessage());
        }

        $message = $result['user_created']
            ? 'Merchant created, plan assigned, billing recorded, and license issued.'
            : 'Existing merchant updated: plan/billing/license provisioned from this landing order.';

        $notify = $result['notifications'] ?? [];
        if (! empty($notify['email']) || ! empty($notify['sms'])) {
            $parts = [];
            if (! empty($notify['email'])) {
                $parts[] = 'email';
            }
            if (! empty($notify['sms'])) {
                $parts[] = 'SMS';
            }
            $message .= ' Notified via '.implode(' + ', $parts).'.';
        } elseif (! empty($notify['errors'])) {
            $message .= ' Merchant notify had issues (check order activity).';
        }

        return back()
            ->with('success', $message)
            ->with('license_token', $result['plain_text_token'])
            ->with('converted_user_id', $result['user']->id)
            ->with('converted_login_email', $result['user']->email)
            ->with('converted_user_created', $result['user_created'])
            ->with('converted_order_id', $order->id)
            ->with('converted_notify_email', (bool) ($notify['email'] ?? false))
            ->with('converted_notify_sms', (bool) ($notify['sms'] ?? false));
    }

    public function show(SubscriptionInquiry $order)
    {
        $order->load([
            'packageHub:id,title,package_price,package_duration',
            'user:id,name,email,phone,acquisition_source,must_change_password',
            'packagePaymentRequest:id,status,total_amount,transaction_id,transaction_method',
        ]);

        $whatsapp = $order->whatsapp_number ?: $order->contact_number;
        $digits = preg_replace('/\D+/', '', (string) $whatsapp) ?? '';
        if ($digits !== '' && ! str_starts_with($digits, '880')) {
            $digits = '880'.ltrim($digits, '0');
        }

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'whatsappUrl' => $digits !== ''
                ? 'https://wa.me/'.$digits.'?text='.rawurlencode(
                    'সালাম, WooEasyLife সাবস্ক্রিপশন অ্যাকাউন্ট নিয়ে যোগাযোগ।'
                )
                : null,
            'auditEvents' => $order->conversion_meta['events'] ?? [],
            'notifications' => $order->conversion_meta['notifications'] ?? null,
        ]);
    }

    public function revealLicense(SubscriptionInquiry $order)
    {
        if ($order->status !== SubscriptionInquiry::STATUS_CONVERTED) {
            return back()->with('error', 'License reveal is only available for converted orders.');
        }

        $token = null;

        if ($order->converted_access_token_id) {
            $token = AccessToken::query()->find($order->converted_access_token_id);
        }

        if (! $token || ! filled($token->access_key)) {
            return back()->with('error', 'No stored license key found for this order.');
        }

        try {
            $plain = Crypt::decryptString($token->access_key);
        } catch (\Throwable) {
            return back()->with('error', 'Could not decrypt the stored license key.');
        }

        return back()
            ->with('success', 'License key loaded.')
            ->with('license_token', $plain)
            ->with('converted_user_id', $order->user_id)
            ->with('converted_login_email', $order->email)
            ->with('converted_user_created', false)
            ->with('converted_order_id', $order->id);
    }
}
