<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInquiry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderAdminController extends Controller
{
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
            'status' => 'required|in:pending,contacted,converted,rejected',
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', 'Order status updated.');
    }
}
