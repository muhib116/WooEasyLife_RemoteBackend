<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\WebhookHubController;
use App\Models\CourierAccount;
use App\Models\CourierForwardRetry;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;
use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\CourierWebhookEventService;
use App\Services\Courier\WordPressCourierForwarder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookActivityController extends Controller
{
    public function __construct(
        protected CourierWebhookEventService $eventService,
        protected CourierForwardRetryService $retryService,
        protected WordPressCourierForwarder $forwarder
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Webhooks/Index');
    }

    public function summary(): JsonResponse
    {
        $lastEvent = CourierWebhookEvent::query()->orderByDesc('id')->first();

        return response()->json([
            'total_events' => CourierWebhookEvent::query()->count(),
            'success_count' => CourierWebhookEvent::query()->where('forward_status', 'success')->count(),
            'failed_count' => CourierWebhookEvent::query()->where('forward_status', 'failed')->count(),
            'retry_queued_count' => CourierWebhookEvent::query()->where('forward_status', 'retry_queued')->count(),
            'orphan_count' => CourierWebhookEvent::query()->where('forward_status', 'orphan')->count(),
            'pending_retries' => CourierForwardRetry::query()->where('status', 'pending')->count(),
            'failed_retries' => CourierForwardRetry::query()->where('status', 'failed')->count(),
            'last_event_at' => $lastEvent?->created_at?->toDateTimeString(),
            'last_forward_status' => $lastEvent?->forward_status,
            'health' => $this->eventService->healthSummary(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $query = CourierWebhookEvent::query()
            ->with('shipment:id,consignment_id,wc_order_id,site_url,status')
            ->orderByDesc('id');

        if ($partner = strtolower(trim((string) $request->query('partner', '')))) {
            $query->where('partner', $partner);
        }

        if ($environment = strtolower(trim((string) $request->query('environment', '')))) {
            $query->where('environment', $environment);
        }

        if ($status = strtolower(trim((string) $request->query('forward_status', '')))) {
            $query->where('forward_status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('consignment_id', 'like', "%{$search}%")
                    ->orWhere('site_url', 'like', "%{$search}%")
                    ->orWhere('forward_message', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('wc_order_id', (int) $search);
                }
            });
        }

        $events = $query
            ->paginate(max(10, min(100, (int) $request->query('per_page', 20))))
            ->through(fn (CourierWebhookEvent $event) => [
                'id' => $event->id,
                'partner' => $event->partner,
                'environment' => $event->environment,
                'consignment_id' => $event->consignment_id,
                'wc_order_id' => $event->wc_order_id,
                'site_url' => $event->site_url,
                'event_type' => $event->event_type,
                'forward_status' => $event->forward_status,
                'forward_message' => $event->forward_message,
                'payload_summary' => $event->payload_summary,
                'created_at' => $event->created_at?->toDateTimeString(),
                'shipment_status' => $event->shipment?->status,
            ]);

        return response()->json($events);
    }

    public function retries(Request $request): JsonResponse
    {
        $query = CourierForwardRetry::query()
            ->with([
                'shipment:id,partner,consignment_id,wc_order_id,site_url,status',
                'webhookEvent:id,partner,forward_status,forward_message,created_at',
            ])
            ->orderByDesc('id');

        if ($status = strtolower(trim((string) $request->query('status', '')))) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['pending', 'failed']);
        }

        $retries = $query
            ->paginate(max(10, min(100, (int) $request->query('per_page', 20))))
            ->through(fn (CourierForwardRetry $retry) => [
                'id' => $retry->id,
                'status' => $retry->status,
                'attempts' => $retry->attempts,
                'max_attempts' => $retry->max_attempts,
                'next_retry_at' => $retry->next_retry_at?->toDateTimeString(),
                'last_attempt_at' => $retry->last_attempt_at?->toDateTimeString(),
                'last_error' => $retry->last_error,
                'created_at' => $retry->created_at?->toDateTimeString(),
                'partner' => $retry->shipment?->partner,
                'consignment_id' => $retry->shipment?->consignment_id,
                'wc_order_id' => $retry->shipment?->wc_order_id,
                'site_url' => $retry->shipment?->site_url,
                'event_id' => $retry->webhook_event_id,
                'event_status' => $retry->webhookEvent?->forward_status,
            ]);

        return response()->json($retries);
    }

    public function processRetries(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->input('limit', 25)));
        $result = $this->retryService->processDueRetries($limit);

        return response()->json([
            'message' => 'Due retries processed.',
            'result' => $result,
        ]);
    }

    public function retryForward(CourierForwardRetry $retry): JsonResponse
    {
        $result = $this->retryService->retryNow($retry);

        return response()->json([
            'message' => $result['message'],
            'success' => $result['success'],
        ], $result['success'] ? 200 : 422);
    }

    public function retryEvent(CourierWebhookEvent $event): JsonResponse
    {
        $result = $this->retryService->retryFromEvent($event);

        return response()->json([
            'message' => $result['message'],
            'success' => $result['success'],
        ], $result['success'] ? 200 : 422);
    }

    public function testPluginReach(CourierWebhookEvent $event): JsonResponse
    {
        $event->load('shipment');

        if (! $event->shipment) {
            return response()->json([
                'success' => false,
                'message' => 'No shipment mapping found for this webhook event.',
                'reachable' => false,
            ], 422);
        }

        $result = $this->forwarder->testPluginReach($event->shipment);

        return response()->json([
            'success' => $result['reachable'],
            'message' => $result['message'],
            'result' => $result,
        ], $result['reachable'] ? 200 : 422);
    }

    public function testSteadfastWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_type' => 'nullable|string|in:delivery_status,tracking_update',
            'consignment_id' => 'nullable|string|max:128',
            'invoice' => 'nullable|string|max:128',
            'environment' => 'nullable|string|in:live,sandbox',
            'auth_token' => 'nullable|string|max:255',
        ]);

        $environment = $validated['environment'] ?? 'live';
        $notificationType = $validated['notification_type'] ?? 'delivery_status';
        $consignmentId = trim((string) ($validated['consignment_id'] ?? ''));

        if ($consignmentId === '') {
            $latestShipment = CourierShipment::query()
                ->where('partner', 'steadfast')
                ->where('environment', $environment)
                ->orderByDesc('id')
                ->first();

            $consignmentId = trim((string) ($latestShipment?->consignment_id ?? '12345'));
        }

        $authToken = trim((string) ($validated['auth_token'] ?? ''));
        if ($authToken === '') {
            $authToken = trim((string) CourierAccount::query()
                ->where('partner', 'steadfast')
                ->where('environment', $environment)
                ->where('is_active', true)
                ->whereNotNull('webhook_verify_secret')
                ->value('webhook_verify_secret'));
        }

        if ($authToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'No Steadfast auth token found. Save Steadfast settings in a store first, or enter the Bearer token manually.',
            ], 422);
        }

        $invoice = trim((string) ($validated['invoice'] ?? ('INV-TEST-'.now()->format('YmdHis'))));
        $updatedAt = now()->format('Y-m-d H:i:s');
        $consignmentValue = ctype_digit($consignmentId) ? (int) $consignmentId : $consignmentId;

        $payload = $notificationType === 'tracking_update'
            ? [
                'notification_type' => 'tracking_update',
                'consignment_id' => $consignmentValue,
                'invoice' => $invoice,
                'tracking_message' => 'Steadfast webhook connectivity test from WooEasyLife admin.',
                'updated_at' => $updatedAt,
            ]
            : [
                'notification_type' => 'delivery_status',
                'consignment_id' => $consignmentValue,
                'invoice' => $invoice,
                'cod_amount' => 1500.00,
                'status' => 'Delivered',
                'delivery_charge' => 100.00,
                'tracking_message' => 'Steadfast webhook connectivity test from WooEasyLife admin.',
                'updated_at' => $updatedAt,
            ];

        $path = '/api/webhooks/steadfast'.($environment === 'sandbox' ? '/sandbox' : '');
        $internalRequest = Request::create(
            $path,
            'POST',
            $payload,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$authToken,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode($payload)
        );

        $beforeEventId = (int) CourierWebhookEvent::query()->max('id');
        $response = app(WebhookHubController::class)->steadfast($internalRequest, $environment);
        $responseBody = json_decode($response->getContent(), true) ?? [];

        $latestEvent = CourierWebhookEvent::query()
            ->where('partner', 'steadfast')
            ->where('id', '>', $beforeEventId)
            ->orderByDesc('id')
            ->first();

        if (! $latestEvent && $consignmentId !== '') {
            $latestEvent = CourierWebhookEvent::query()
                ->where('partner', 'steadfast')
                ->where('consignment_id', (string) $consignmentId)
                ->orderByDesc('id')
                ->first();
        }

        $callbackUrl = rtrim((string) config('app.url'), '/').$path;
        $docsCompliant = ($responseBody['status'] ?? '') === 'success'
            && $response->getStatusCode() === 200
            && ($responseBody['message'] ?? '') === 'Webhook received successfully.';

        return response()->json([
            'success' => $docsCompliant,
            'message' => $responseBody['message'] ?? 'Steadfast webhook test completed.',
            'http_status' => $response->getStatusCode(),
            'callback_url' => $callbackUrl,
            'payload' => $payload,
            'response' => $responseBody,
            'docs_compliant' => $docsCompliant,
            'event' => $latestEvent ? [
                'id' => $latestEvent->id,
                'forward_status' => $latestEvent->forward_status,
                'forward_message' => $latestEvent->forward_message,
                'consignment_id' => $latestEvent->consignment_id,
                'wc_order_id' => $latestEvent->wc_order_id,
            ] : null,
        ], $docsCompliant ? 200 : 422);
    }
}
