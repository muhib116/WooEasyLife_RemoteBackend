<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\WebhookHubController;
use App\Models\CourierAccount;
use App\Models\CourierForwardRetry;
use App\Models\CourierHubToken;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;
use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\CourierWebhookEventService;
use App\Services\Courier\WordPressCourierForwarder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WebhookActivityController extends Controller
{
    private const ADMIN_TEST_SOURCE = 'admin_test';

    private const BULK_DELETE_WARNING_THRESHOLD = 50;

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
        $lastEvent = $this->excludeAdminTestEvents(CourierWebhookEvent::query())->orderByDesc('id')->first();

        return response()->json([
            'total_events' => $this->excludeAdminTestEvents(CourierWebhookEvent::query())->count(),
            'success_count' => $this->excludeAdminTestEvents(CourierWebhookEvent::query())->where('forward_status', 'success')->count(),
            'failed_count' => $this->excludeAdminTestEvents(CourierWebhookEvent::query())->where('forward_status', 'failed')->count(),
            'retry_queued_count' => $this->excludeAdminTestEvents(CourierWebhookEvent::query())->where('forward_status', 'retry_queued')->count(),
            'orphan_count' => $this->excludeAdminTestEvents(CourierWebhookEvent::query())->where('forward_status', 'orphan')->count(),
            'admin_test_count' => CourierWebhookEvent::query()->where('payload_summary->source', self::ADMIN_TEST_SOURCE)->count(),
            'pending_retries' => CourierForwardRetry::query()->where('status', 'pending')->count(),
            'failed_retries' => CourierForwardRetry::query()->where('status', 'failed')->count(),
            'last_event_at' => $lastEvent?->created_at?->toDateTimeString(),
            'last_forward_status' => $lastEvent?->forward_status,
            'health' => $this->eventService->healthSummary(),
            'bulk_delete_warning_threshold' => self::BULK_DELETE_WARNING_THRESHOLD,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $events = $this->buildEventsQuery($request)
            ->paginate(max(10, min(100, (int) $request->query('per_page', 20))))
            ->through(fn (CourierWebhookEvent $event) => $this->formatWebhookEvent($event));

        return response()->json($events);
    }

    public function deleteEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required_without:select_all|array',
            'ids.*' => 'integer',
            'select_all' => 'nullable|boolean',
            'partner' => 'nullable|string|max:32',
            'environment' => 'nullable|string|max:16',
            'forward_status' => 'nullable|string|max:32',
            'search' => 'nullable|string|max:255',
            'source' => 'nullable|string|in:all,courier,admin_test',
        ]);

        $ids = $this->resolveDeletionIds(
            $request->boolean('select_all'),
            $validated['ids'] ?? [],
            $this->buildEventsQuery($request)
        );

        if ($ids === []) {
            return response()->json([
                'message' => 'No webhook events selected for deletion.',
                'deleted' => 0,
            ], 422);
        }

        $deleted = $this->deleteWebhookEventsByIds($ids);

        if (! $request->boolean('select_all') && $deleted === 0) {
            return response()->json([
                'message' => 'No matching webhook events were found for the selected IDs.',
                'deleted' => 0,
            ], 422);
        }

        return response()->json([
            'message' => "{$deleted} webhook event(s) deleted.",
            'deleted' => $deleted,
        ]);
    }

    public function retries(Request $request): JsonResponse
    {
        $retries = $this->buildRetriesQuery($request)
            ->paginate(max(10, min(100, (int) $request->query('per_page', 20))))
            ->through(fn (CourierForwardRetry $retry) => $this->formatForwardRetry($retry));

        return response()->json($retries);
    }

    public function deleteRetries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required_without:select_all|array',
            'ids.*' => 'integer',
            'select_all' => 'nullable|boolean',
            'status' => 'nullable|string|max:32',
        ]);

        $ids = $this->resolveDeletionIds(
            $request->boolean('select_all'),
            $validated['ids'] ?? [],
            $this->buildRetriesQuery($request)
        );

        if ($ids === []) {
            return response()->json([
                'message' => 'No retry rows selected for deletion.',
                'deleted' => 0,
            ], 422);
        }

        $deleted = CourierForwardRetry::query()->whereIn('id', $ids)->delete();

        if (! $request->boolean('select_all') && $deleted === 0) {
            return response()->json([
                'message' => 'No matching retry rows were found for the selected IDs.',
                'deleted' => 0,
            ], 422);
        }

        return response()->json([
            'message' => "{$deleted} retry row(s) deleted.",
            'deleted' => $deleted,
        ]);
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

    public function testCourierWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner' => 'required|string|in:steadfast,pathao,redx',
            'test_type' => 'nullable|string',
            'consignment_id' => 'nullable|string|max:128',
            'invoice' => 'nullable|string|max:128',
            'environment' => 'nullable|string|in:live,sandbox',
            'auth_token' => 'nullable|string|max:255',
        ]);

        $partner = strtolower(trim($validated['partner']));
        $environment = $validated['environment'] ?? 'live';
        $testType = strtolower(trim((string) ($validated['test_type'] ?? $this->defaultTestType($partner))));
        $consignmentId = $this->resolveTestConsignmentId($partner, $environment, $validated['consignment_id'] ?? null);
        $authToken = trim((string) ($validated['auth_token'] ?? ''));

        if ($authToken === '') {
            $authToken = $this->resolveWebhookAuthToken($partner, $environment);
        }

        if ($partner !== 'pathao' || ! in_array($testType, ['webhook_integration', 'panel_test'], true)) {
            if ($authToken === '') {
                return response()->json([
                    'success' => false,
                    'message' => $this->missingAuthMessage($partner),
                ], 422);
            }
        }

        [$path, $payload, $server] = $this->buildCourierWebhookTestRequest(
            $partner,
            $environment,
            $testType,
            $consignmentId,
            trim((string) ($validated['invoice'] ?? '')),
            $authToken
        );

        $beforeEventId = (int) CourierWebhookEvent::query()->max('id');
        $controller = app(WebhookHubController::class);
        $response = match ($partner) {
            'pathao' => $controller->pathao($this->createInternalRequest($path, $payload, $server), $environment),
            'redx' => $controller->redx($this->createInternalRequest($path, $payload, $server), $environment),
            default => $controller->steadfast($this->createInternalRequest($path, $payload, $server), $environment),
        };

        $responseBody = json_decode($response->getContent(), true) ?? [];
        $latestEvent = $this->findLatestTestEvent($partner, $beforeEventId, $consignmentId);
        if ($latestEvent) {
            $this->markEventAsAdminTest($latestEvent, $testType);
        }
        $callbackUrl = rtrim((string) config('app.url'), '/').$path;
        $docsCompliant = $this->isDocsCompliantResponse($partner, $testType, $response, $responseBody);

        return response()->json([
            'success' => $docsCompliant,
            'message' => $responseBody['message'] ?? ($responseBody['status'] ?? 'Webhook test completed.'),
            'partner' => $partner,
            'test_type' => $testType,
            'http_status' => $response->getStatusCode(),
            'callback_url' => $callbackUrl,
            'payload' => $payload,
            'response' => $responseBody,
            'response_headers' => $this->flattenResponseHeaders($response),
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

    public function testSteadfastWebhook(Request $request): JsonResponse
    {
        $request->merge([
            'partner' => 'steadfast',
            'test_type' => $request->input('notification_type', $request->input('test_type', 'delivery_status')),
        ]);

        return $this->testCourierWebhook($request);
    }

    private function defaultTestType(string $partner): string
    {
        return match ($partner) {
            'pathao' => 'webhook_integration',
            'redx' => 'delivered',
            default => 'delivery_status',
        };
    }

    private function resolveTestConsignmentId(string $partner, string $environment, ?string $consignmentId): string
    {
        $consignmentId = trim((string) ($consignmentId ?? ''));

        if ($consignmentId !== '') {
            return $consignmentId;
        }

        $latestShipment = CourierShipment::query()
            ->where('partner', $partner)
            ->where('environment', $environment)
            ->orderByDesc('id')
            ->first();

        if ($latestShipment?->consignment_id) {
            return trim((string) $latestShipment->consignment_id);
        }

        return match ($partner) {
            'pathao' => 'DL121224VS8TTJ',
            'redx' => 'RX-TEST-'.now()->format('YmdHis'),
            default => '12345',
        };
    }

    private function resolveWebhookAuthToken(string $partner, string $environment): string
    {
        $secret = CourierAccount::query()
            ->where('partner', $partner)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->whereNotNull('webhook_verify_secret')
            ->value('webhook_verify_secret');

        if (filled($secret)) {
            return trim((string) $secret);
        }

        if ($partner === 'redx') {
            return trim((string) CourierHubToken::tokenForPartner('redx'));
        }

        return '';
    }

    private function missingAuthMessage(string $partner): string
    {
        return match ($partner) {
            'pathao' => 'No Pathao webhook secret found. Save Pathao settings in a store first, or enter the X-PATHAO-Signature value manually.',
            'redx' => 'No RedX webhook token found. Save RedX settings in a store first, or enter the token manually.',
            default => 'No Steadfast auth token found. Save Steadfast settings in a store first, or enter the Bearer token manually.',
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, string>}
     */
    private function buildCourierWebhookTestRequest(
        string $partner,
        string $environment,
        string $testType,
        string $consignmentId,
        string $invoice,
        string $authToken
    ): array {
        $updatedAt = now()->format('Y-m-d H:i:s');
        $invoice = $invoice !== '' ? $invoice : 'INV-TEST-'.now()->format('YmdHis');
        $consignmentValue = ctype_digit($consignmentId) ? (int) $consignmentId : $consignmentId;
        $path = '/api/webhooks/'.$partner.($environment === 'sandbox' ? '/sandbox' : '');
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($partner === 'steadfast') {
            $payload = $testType === 'tracking_update'
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

            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$authToken;

            return [$path, $payload, $server];
        }

        if ($partner === 'pathao') {
            if (in_array($testType, ['webhook_integration', 'panel_test'], true)) {
                $payload = [
                    'event' => 'webhook_integration',
                    'consignment_id' => $consignmentId,
                    'merchant_order_id' => 'TS-123',
                ];

                return [$path, $payload, $server];
            }

            $payload = [
                'event' => $testType === 'order_delivered' ? 'order.delivered' : 'order.picked_up',
                'consignment_id' => $consignmentId,
                'merchant_order_id' => 'TS-'.now()->format('YmdHis'),
                'updated_at' => $updatedAt,
                'timestamp' => $updatedAt,
            ];

            $server['HTTP_X_PATHAO_SIGNATURE'] = $authToken;
            $server['HTTP_X-Pathao-Signature'] = $authToken;

            return [$path, $payload, $server];
        }

        $payload = [
            'tracking_number' => $consignmentId,
            'invoice_number' => $invoice,
            'status' => $testType === 'partial_delivery' ? 'delivered' : 'delivered',
            'delivery_type' => $testType === 'partial_delivery' ? 'partial-delivery' : 'regular',
            'message_en' => 'RedX webhook connectivity test from WooEasyLife admin.',
            'message_bn' => 'RedX webhook connectivity test from WooEasyLife admin.',
            'timestamp' => $updatedAt,
        ];

        $path .= (str_contains($path, '?') ? '&' : '?').'token='.urlencode($authToken);

        return [$path, $payload, $server];
    }

    private function createInternalRequest(string $path, array $payload, array $server): Request
    {
        return Request::create(
            $path,
            'POST',
            $payload,
            [],
            [],
            $server,
            json_encode($payload)
        );
    }

    private function findLatestTestEvent(string $partner, int $beforeEventId, string $consignmentId): ?CourierWebhookEvent
    {
        $latestEvent = CourierWebhookEvent::query()
            ->where('partner', $partner)
            ->where('id', '>', $beforeEventId)
            ->orderByDesc('id')
            ->first();

        if ($latestEvent || $consignmentId === '') {
            return $latestEvent;
        }

        return CourierWebhookEvent::query()
            ->where('partner', $partner)
            ->where('consignment_id', (string) $consignmentId)
            ->orderByDesc('id')
            ->first();
    }

    private function isDocsCompliantResponse(string $partner, string $testType, $response, array $responseBody): bool
    {
        if ($partner === 'pathao' && in_array($testType, ['webhook_integration', 'panel_test'], true)) {
            return $response->getStatusCode() === 202
                && ($responseBody['status'] ?? '') === 'accepted';
        }

        if ($partner === 'pathao') {
            return $response->getStatusCode() === 202
                && ($responseBody['status'] ?? '') === 'success';
        }

        return $response->getStatusCode() === 200
            && ($responseBody['status'] ?? '') === 'success'
            && ($responseBody['message'] ?? '') === 'Webhook received successfully.';
    }

    /**
     * @return array<string, string>
     */
    private function flattenResponseHeaders($response): array
    {
        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            $headers[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
        }

        return $headers;
    }

    private function buildEventsQuery(Request $request)
    {
        $query = CourierWebhookEvent::query()
            ->with('shipment:id,consignment_id,wc_order_id,site_url,status')
            ->orderByDesc('id');

        if ($partner = strtolower(trim((string) $request->query('partner', $request->input('partner', ''))))) {
            $query->where('partner', $partner);
        }

        if ($environment = strtolower(trim((string) $request->query('environment', $request->input('environment', ''))))) {
            $query->where('environment', $environment);
        }

        if ($status = strtolower(trim((string) $request->query('forward_status', $request->input('forward_status', ''))))) {
            $query->where('forward_status', $status);
        }

        if ($search = trim((string) $request->query('search', $request->input('search', '')))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('consignment_id', 'like', "%{$search}%")
                    ->orWhere('site_url', 'like', "%{$search}%")
                    ->orWhere('forward_message', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('wc_order_id', (int) $search);
                }
            });
        }

        $this->applyEventSourceFilter(
            $query,
            (string) $request->query('source', $request->input('source', 'courier'))
        );

        return $query;
    }

    private function applyEventSourceFilter($query, string $source): void
    {
        $source = strtolower(trim($source));

        if ($source === 'admin_test') {
            $query->where('payload_summary->source', self::ADMIN_TEST_SOURCE);

            return;
        }

        if ($source === 'courier') {
            $this->excludeAdminTestEvents($query);
        }
    }

    private function excludeAdminTestEvents($query)
    {
        return $query->where(function ($builder) {
            $builder->whereNull('payload_summary')
                ->orWhere('payload_summary->source', '!=', self::ADMIN_TEST_SOURCE);
        });
    }

    private function markEventAsAdminTest(CourierWebhookEvent $event, string $testType): void
    {
        $summary = is_array($event->payload_summary) ? $event->payload_summary : [];
        $summary['source'] = self::ADMIN_TEST_SOURCE;
        $summary['admin_test_type'] = $testType;

        $event->payload_summary = $summary;
        $event->save();
    }

    private function buildRetriesQuery(Request $request)
    {
        $query = CourierForwardRetry::query()
            ->with([
                'shipment:id,partner,consignment_id,wc_order_id,site_url,status',
                'webhookEvent:id,partner,forward_status,forward_message,created_at',
            ])
            ->orderByDesc('id');

        if ($status = strtolower(trim((string) $request->query('status', $request->input('status', ''))))) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['pending', 'failed']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWebhookEvent(CourierWebhookEvent $event): array
    {
        return [
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
            'is_admin_test' => ($event->payload_summary['source'] ?? null) === self::ADMIN_TEST_SOURCE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatForwardRetry(CourierForwardRetry $retry): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function resolveDeletionIds(bool $selectAll, array $ids, $query): array
    {
        if ($selectAll) {
            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function deleteWebhookEventsByIds(array $ids): int
    {
        return (int) DB::transaction(function () use ($ids) {
            CourierForwardRetry::query()
                ->whereIn('webhook_event_id', $ids)
                ->delete();

            return CourierWebhookEvent::query()->whereIn('id', $ids)->delete();
        });
    }
}
