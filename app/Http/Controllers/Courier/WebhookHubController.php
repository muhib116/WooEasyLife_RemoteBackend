<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use App\Models\CourierHubToken;
use App\Models\CourierShipment;
use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\CourierWebhookEventService;
use App\Services\Courier\WordPressCourierForwarder;
use Illuminate\Http\Request;

class WebhookHubController extends Controller
{
    /** Pathao integration verification header value. */
    public const PATHAO_INTEGRATION_SECRET = 'f3992ecc-59da-4cbe-a049-a13da2018d51';

    public function __construct(
        protected CourierShipmentService $shipmentService,
        protected WordPressCourierForwarder $forwarder,
        protected CourierWebhookEventService $eventService,
        protected CourierForwardRetryService $retryService
    ) {
    }

    public function pathao(Request $request, string $environment = 'live')
    {
        $payload = $request->all();
        $event = strtolower(str_replace('.', '_', (string) ($payload['event'] ?? '')));

        if ($event === 'webhook_integration') {
            return response()->json(['status' => 'accepted'], 202, [
                'X-Pathao-Merchant-Webhook-Integration-Secret' => self::PATHAO_INTEGRATION_SECRET,
            ]);
        }

        if (!$this->verifyPathaoSignature($request, $environment)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        if (!$this->isPathaoOrderEvent((string) ($payload['event'] ?? ''))) {
            return response()->json(['status' => 'accepted'], 202);
        }

        $consignmentId = trim((string) ($payload['consignment_id'] ?? ''));
        if ($consignmentId === '') {
            return response()->json(['status' => 'accepted'], 202);
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
        if (!$this->verifySteadfastAuth($request, $environment)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        $payload = $request->all();
        $notificationType = strtolower((string) ($payload['notification_type'] ?? 'delivery_status'));
        $consignmentId = trim((string) ($payload['consignment_id'] ?? ''));

        if ($consignmentId === '') {
            return response()->json(['status' => 'success', 'message' => 'Webhook received successfully.']);
        }

        if ($notificationType === 'tracking_update') {
            return $this->forwardShipmentUpdate('steadfast', $consignmentId, [
                'partner' => 'steadfast',
                'notification_type' => 'tracking_update',
                'tracking_message' => (string) ($payload['tracking_message'] ?? ''),
                'updated_at' => (string) ($payload['updated_at'] ?? now()->toDateTimeString()),
            ], $environment);
        }

        return $this->forwardShipmentUpdate('steadfast', $consignmentId, [
            'partner' => 'steadfast',
            'raw_status' => (string) ($payload['status'] ?? ''),
            'updated_at' => (string) ($payload['updated_at'] ?? now()->toDateTimeString()),
            'tracking_message' => (string) ($payload['tracking_message'] ?? ''),
        ], $environment);
    }

    public function redx(Request $request, string $environment = 'live')
    {
        if (!$this->verifyRedxToken($request)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        $payload = $request->all();
        $trackingNumber = trim((string) ($payload['tracking_number'] ?? ''));
        $rawStatus = (string) ($payload['status'] ?? '');
        $deliveryType = strtolower(str_replace('-', '_', (string) ($payload['delivery_type'] ?? '')));

        if (in_array($deliveryType, ['partial_delivery', 'partial_return'], true)) {
            $rawStatus = 'partial_delivery';
        }

        if ($trackingNumber === '') {
            return response()->json(['status' => 'success', 'message' => 'Webhook received successfully.']);
        }

        return $this->forwardShipmentUpdate('redx', $trackingNumber, [
            'partner' => 'redx',
            'raw_status' => $rawStatus,
            'updated_at' => (string) ($payload['timestamp'] ?? now()->toDateTimeString()),
            'tracking_message' => (string) ($payload['message_en'] ?? ''),
        ], $environment);
    }

    private function forwardShipmentUpdate(string $partner, string $consignmentId, array $payload, string $environment = 'live')
    {
        $shipment = $this->shipmentService->findByConsignment($partner, $consignmentId, $environment);

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

        if (!$shipment || !$shipment->wc_order_id) {
            $event->forward_status = 'orphan';
            $event->forward_message = 'mapping_not_found';
            $event->save();

            return response()->json([
                'status' => 'accepted',
                'message' => 'Shipment mapping not found.',
            ], in_array($partner, ['pathao'], true) ? 202 : 200);
        }

        $forwardPayload = array_merge($payload, [
            'order_id' => (int) $shipment->wc_order_id,
            'courier_account_id' => (int) $shipment->courier_account_id,
        ]);

        $result = $this->forwarder->forward($shipment, $forwardPayload);

        if (!empty($result['success'])) {
            $shipment->status = (string) ($payload['raw_status'] ?? $shipment->status);
            $shipment->last_webhook_at = now();
            $shipment->save();

            $event->forward_status = 'success';
            $event->forward_message = 'forwarded';
            $event->save();

            return response()->json([
                'status' => 'success',
                'message' => $result['message'] ?? 'forwarded',
            ], in_array($partner, ['pathao'], true) ? 202 : 200);
        }

        $error = (string) ($result['message'] ?? 'forward_failed');
        $event->forward_status = 'retry_queued';
        $event->forward_message = $error;
        $event->save();

        $this->retryService->queueRetry($shipment, $forwardPayload, $event, $error);

        return response()->json([
            'status' => 'success',
            'message' => 'Forward queued for retry.',
        ], in_array($partner, ['pathao'], true) ? 202 : 200);
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
        $auth = trim((string) $request->bearerToken());
        if ($auth === '') {
            return false;
        }

        $consignmentId = trim((string) $request->input('consignment_id', ''));
        $account = null;

        if ($consignmentId !== '') {
            $shipment = $this->shipmentService->findByConsignment('steadfast', $consignmentId, $environment);
            if ($shipment?->courier_account_id) {
                $account = CourierAccount::query()->find($shipment->courier_account_id);
            }
        }

        $candidates = CourierAccount::query()
            ->where('partner', 'steadfast')
            ->where('environment', $environment === 'sandbox' ? 'sandbox' : 'live')
            ->where('is_active', true)
            ->when($account, fn ($query) => $query->where('id', $account->id))
            ->get();

        foreach ($candidates as $candidate) {
            $secret = trim((string) $candidate->webhook_verify_secret);
            if ($secret !== '' && hash_equals($secret, $auth)) {
                return true;
            }
        }

        return false;
    }

    private function verifyRedxToken(Request $request): bool
    {
        $token = trim((string) $request->query('token'));
        if ($token === '') {
            return false;
        }

        $expected = CourierHubToken::tokenForPartner('redx');

        return $expected !== '' && hash_equals($expected, $token);
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
}
