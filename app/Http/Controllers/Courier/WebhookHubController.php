<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use App\Models\CourierHubToken;
use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\CourierWebhookEventService;
use App\Services\Courier\SteadfastWebhookProcessor;
use App\Services\Courier\WordPressCourierForwarder;
use App\Services\OrderIntelligence\WebhookOrderIngestor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookHubController extends Controller
{
    /** Pathao integration verification header value. */
    public const PATHAO_INTEGRATION_SECRET = 'f3992ecc-59da-4cbe-a049-a13da2018d51';

    public function __construct(
        protected CourierShipmentService $shipmentService,
        protected WordPressCourierForwarder $forwarder,
        protected CourierWebhookEventService $eventService,
        protected CourierForwardRetryService $retryService,
        protected WebhookOrderIngestor $webhookOrderIngestor,
        protected SteadfastWebhookProcessor $steadfastWebhooks,
    ) {}

    public function pathao(Request $request, string $environment = 'live')
    {
        $payload = $request->all();
        $event = strtolower(str_replace('.', '_', (string) ($payload['event'] ?? '')));

        if ($event === 'webhook_integration' || $this->isPathaoPanelTest($payload)) {
            return $this->pathaoAcceptedResponse();
        }

        if (! $this->verifyPathaoSignature($request, $environment)) {
            return $this->pathaoResponse(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        if (! $this->isPathaoOrderEvent((string) ($payload['event'] ?? ''))) {
            return $this->pathaoAcceptedResponse();
        }

        $consignmentId = trim((string) ($payload['consignment_id'] ?? ''));
        if ($consignmentId === '') {
            return $this->pathaoAcceptedResponse();
        }

        return $this->forwardShipmentUpdate('pathao', $consignmentId, [
            'partner' => 'pathao',
            'raw_status' => (string) ($payload['event'] ?? ''),
            'updated_at' => (string) ($payload['updated_at'] ?? ($payload['timestamp'] ?? now()->toDateTimeString())),
            'tracking_message' => '',
        ], $environment);
    }

    public function steadfast(Request $request, string $environment = 'live')
    {
        if (! $this->verifySteadfastAuth($request, $environment)) {
            return $this->steadfastErrorResponse('Unauthorized.', 401);
        }

        $this->steadfastWebhooks->receive($request, $environment);

        return $this->steadfastSuccessResponse();
    }

    public function redx(Request $request, string $environment = 'live')
    {
        if (! $this->verifyRedxToken($request, $environment)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        $payload = $request->all();
        $trackingNumber = trim((string) ($payload['tracking_number'] ?? ''));
        $invoice = trim((string) ($payload['invoice_number'] ?? ''));

        if ($trackingNumber === '') {
            return $this->standardCourierWebhookSuccessResponse(200);
        }

        $rawStatus = $this->normalizeRedxWebhookStatus(
            (string) ($payload['status'] ?? ''),
            (string) ($payload['delivery_type'] ?? '')
        );

        if ($rawStatus === '') {
            return $this->standardCourierWebhookSuccessResponse(200);
        }

        $messageEn = trim((string) ($payload['message_en'] ?? ''));
        $messageBn = trim((string) ($payload['message_bn'] ?? ''));
        $trackingMessage = $messageEn;
        if ($messageBn !== '' && $messageBn !== $messageEn) {
            $trackingMessage = $messageEn !== '' ? $messageEn.' / '.$messageBn : $messageBn;
        }

        return $this->forwardShipmentUpdate('redx', $trackingNumber, [
            'partner' => 'redx',
            'raw_status' => $rawStatus,
            'updated_at' => (string) ($payload['timestamp'] ?? now()->toDateTimeString()),
            'tracking_message' => $trackingMessage,
            'invoice' => $invoice,
            'delivery_type' => trim((string) ($payload['delivery_type'] ?? '')),
        ], $environment, $invoice);
    }

    private function forwardShipmentUpdate(
        string $partner,
        string $consignmentId,
        array $payload,
        string $environment = 'live',
        string $invoice = ''
    ) {
        $shipment = $this->shipmentService->resolveShipment(
            $partner,
            $consignmentId,
            $invoice !== '' ? $invoice : (string) ($payload['invoice'] ?? ''),
            $environment
        );

        $event = $this->eventService->record($partner, $environment, [
            'consignment_id' => $consignmentId,
            'shipment_id' => $shipment?->id,
            'access_token_id' => $shipment?->access_token_id,
            'site_url' => $shipment?->site_url,
            'wc_order_id' => $shipment?->wc_order_id,
            'event_type' => (string) ($payload['raw_status'] ?? ($payload['notification_type'] ?? 'webhook')),
            'forward_status' => 'received',
            'payload_summary' => [
                'partner' => $partner,
                'consignment_id' => $consignmentId,
            ],
        ]);

        if (! $shipment || ! $shipment->wc_order_id) {
            $event->forward_status = 'orphan';
            $event->forward_message = 'mapping_not_found';
            $event->save();

            return $this->partnerResponse($partner, [
                'status' => 'success',
                'message' => 'Webhook received successfully.',
            ], $this->successStatusForPartner($partner));
        }

        $this->webhookOrderIngestor->ingest($shipment, $payload, $event);

        $forwardPayload = array_merge($payload, [
            'order_id' => (int) $shipment->wc_order_id,
            'courier_account_id' => (int) $shipment->courier_account_id,
        ]);

        $result = $this->forwarder->forward($shipment, $forwardPayload);

        if (! empty($result['success'])) {
            $shipment->status = (string) ($payload['raw_status'] ?? $shipment->status);
            $shipment->last_webhook_at = now();
            $shipment->save();

            $event->forward_status = 'success';
            $event->forward_message = 'forwarded';
            $event->save();

            return $this->partnerResponse($partner, [
                'status' => 'success',
                'message' => 'Webhook received successfully.',
            ], $this->successStatusForPartner($partner));
        }

        $error = (string) ($result['message'] ?? 'forward_failed');
        $event->forward_status = 'retry_queued';
        $event->forward_message = $error;
        $event->save();

        $this->retryService->queueRetry($shipment, $forwardPayload, $event, $error);

        return $this->partnerResponse($partner, [
            'status' => 'success',
            'message' => 'Webhook received successfully.',
        ], $this->successStatusForPartner($partner));
    }

    private function verifyPathaoSignature(Request $request, string $environment): bool
    {
        $signature = trim((string) ($request->header('X-PATHAO-Signature') ?: $request->header('x-pathao-signature')));
        if ($signature === '') {
            return false;
        }

        $consignmentId = trim((string) $request->input('consignment_id', ''));
        $account = null;

        if ($consignmentId !== '') {
            $shipment = $this->shipmentService->findByConsignment('pathao', $consignmentId, $environment);
            if ($shipment?->courier_account_id) {
                $account = CourierAccount::query()->find($shipment->courier_account_id);
            }
        }

        $candidates = CourierAccount::query()
            ->where('partner', 'pathao')
            ->where('environment', $environment === 'sandbox' ? 'sandbox' : 'live')
            ->where('is_active', true)
            ->when($account, fn ($query) => $query->where('id', $account->id))
            ->get();

        foreach ($candidates as $candidate) {
            $secret = trim((string) $candidate->webhook_verify_secret);
            if ($secret !== '' && hash_equals($secret, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function verifySteadfastAuth(Request $request, string $environment): bool
    {
        $bearer = trim((string) $request->bearerToken());
        $signature = strtolower(trim((string) $request->header('X-Signature', '')));

        if ($bearer === '' && $signature === '') {
            return false;
        }

        $consignmentId = trim((string) $request->input('consignment_id', ''));
        $rawBody = (string) $request->getContent();
        $secrets = $this->steadfastSecrets($request, $environment);
        $bearerMatched = false;

        foreach ($secrets as $secret) {
            $signatureMatches = $signature !== '' && $this->steadfastSignatureMatches($rawBody, $secret, $signature);
            $bearerMatches = $bearer !== ''
                && strlen($bearer) === strlen($secret)
                && hash_equals($secret, $bearer);

            if ($signature === '') {
                if ($bearerMatches) {
                    return true;
                }

                continue;
            }

            if ($signatureMatches && ($bearer === '' || $bearerMatches)) {
                return true;
            }

            if ($bearerMatches) {
                $bearerMatched = true;
            }
        }

        if ($signature !== '' && $bearerMatched) {
            Log::warning('steadfast_signature_mismatch', [
                'partner' => 'steadfast',
                'consignment_id' => $consignmentId,
                'signature_header_present' => true,
            ]);

            return ! config('courier.steadfast_enforce_webhook_signature');
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function steadfastSecrets(Request $request, string $environment): array
    {
        $consignmentId = trim((string) $request->input('consignment_id', ''));
        $account = null;

        if ($consignmentId !== '') {
            $shipment = $this->shipmentService->resolveShipment('steadfast', $consignmentId, '', $environment);
            if ($shipment?->courier_account_id) {
                $account = CourierAccount::query()->find($shipment->courier_account_id);
            }
        }

        return CourierAccount::query()
            ->where('partner', 'steadfast')
            ->where('environment', $environment === 'sandbox' ? 'sandbox' : 'live')
            ->where('is_active', true)
            ->when($account, fn ($query) => $query->where('id', $account->id))
            ->pluck('webhook_verify_secret')
            ->map(fn ($secret) => trim((string) $secret))
            ->filter(fn ($secret) => $secret !== '')
            ->values()
            ->all();
    }

    private function steadfastSignatureMatches(string $rawBody, string $secret, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (strlen($signature) !== strlen($expected)) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    private function verifyRedxToken(Request $request, string $environment = 'live'): bool
    {
        $token = trim((string) $request->query('token'));
        if ($token === '') {
            return false;
        }

        $env = $environment === 'sandbox' ? 'sandbox' : 'live';

        $accountSecrets = CourierAccount::query()
            ->where('partner', 'redx')
            ->where('environment', $env)
            ->where('is_active', true)
            ->pluck('webhook_verify_secret')
            ->filter(fn ($secret) => trim((string) $secret) !== '')
            ->all();

        foreach ($accountSecrets as $secret) {
            if (hash_equals((string) $secret, $token)) {
                return true;
            }
        }

        $expected = CourierHubToken::tokenForPartner('redx');

        return $expected !== '' && hash_equals($expected, $token);
    }

    private function isPathaoPanelTest(array $payload): bool
    {
        $consignmentId = trim((string) ($payload['consignment_id'] ?? ''));
        $merchantOrderId = trim((string) ($payload['merchant_order_id'] ?? ''));

        return $consignmentId === 'DL121224VS8TTJ' || $merchantOrderId === 'TS-123';
    }

    private function pathaoAcceptedResponse()
    {
        return $this->pathaoResponse(['status' => 'accepted'], 202);
    }

    private function pathaoResponse(array $body, int $status)
    {
        return response()->json($body, $status, [
            'X-Pathao-Merchant-Webhook-Integration-Secret' => self::PATHAO_INTEGRATION_SECRET,
        ]);
    }

    private function partnerResponse(string $partner, array $body, int $status)
    {
        if ($partner === 'pathao') {
            return $this->pathaoResponse($body, $status);
        }

        if (in_array($partner, ['steadfast', 'redx'], true)) {
            if (($body['status'] ?? '') === 'error') {
                return response()->json($body, $status);
            }

            return $this->standardCourierWebhookSuccessResponse($status);
        }

        return response()->json($body, $status);
    }

    private function steadfastSuccessResponse()
    {
        return $this->standardCourierWebhookSuccessResponse(200);
    }

    private function steadfastErrorResponse(string $message, int $status = 401)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }

    private function standardCourierWebhookSuccessResponse(int $status = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received successfully.',
        ], $status);
    }

    private function successStatusForPartner(string $partner): int
    {
        return $partner === 'pathao' ? 202 : 200;
    }

    private function isPathaoOrderEvent(string $event): bool
    {
        $normalized = strtolower(str_replace('.', '_', trim($event)));

        if ($normalized === '' || $normalized === 'webhook_integration') {
            return false;
        }

        $ignored = ['payment_invoice', 'store_created', 'store_updated', 'store_deleted'];
        if (in_array($normalized, $ignored, true)) {
            return false;
        }

        return str_starts_with($normalized, 'order')
            || in_array($normalized, [
                'picked_up', 'delivered', 'partial_delivery', 'returned', 'cancelled',
                'delivery_failed', 'pickup_requested', 'assigned_for_pickup',
                'assigned_for_delivery', 'in_transit', 'out_for_delivery',
                'return_initiated', 'return_completed',
            ], true);
    }

    /**
     * Normalize RedX webhook status and delivery_type per merchant portal docs.
     */
    private function normalizeRedxWebhookStatus(string $status, string $deliveryType = ''): string
    {
        $deliveryType = strtolower(trim(str_replace([' ', '-'], '_', $deliveryType)));

        if ($deliveryType === 'partial_delivery') {
            return 'partial_delivered';
        }

        if ($deliveryType === 'partial_return') {
            return 'return_in_transit';
        }

        $raw = strtolower(trim(str_replace([' ', '-'], '_', $status)));
        if ($raw === '') {
            return '';
        }

        $aliases = [
            'ready_for_delivery' => 'pending',
            'delivery_in_progress' => 'in_transit',
            'delivered' => 'delivered',
            'agent_hold' => 'hold',
            'agent_returning' => 'return_in_transit',
            'agent_area_change' => 'in_transit',
            'returned' => 'returned',
            'partial_delivery' => 'partial_delivered',
            'partial_return' => 'return_in_transit',
        ];

        return $aliases[$raw] ?? $raw;
    }
}
