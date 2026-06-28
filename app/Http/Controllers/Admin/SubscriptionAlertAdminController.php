<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionAlertService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionAlertAdminController extends Controller
{
    public function __construct(
        protected SubscriptionAlertService $subscriptionAlertService
    ) {
    }

    public function index(Request $request)
    {
        $severity = $request->query('severity', 'all');
        $feed = $this->subscriptionAlertService->adminAlertFeed(200);

        if ($severity !== 'all') {
            $feed = $feed->where('severity', $severity)->values();
        }

        return Inertia::render('SubscriptionAlerts/Index', [
            'alerts' => $feed,
            'summary' => $this->subscriptionAlertService->summarizeAdminFeed(
                $this->subscriptionAlertService->adminAlertFeed(500)
            ),
            'severity' => $severity,
            'notifications_enabled' => (bool) config('subscription.notifications.enabled', true),
            'notification_channels' => [
                'email' => (bool) config('subscription.notifications.email', true),
                'sms' => (bool) config('subscription.notifications.sms', false),
                'whatsapp' => (bool) config('subscription.notifications.whatsapp', false),
            ],
        ]);
    }
}
