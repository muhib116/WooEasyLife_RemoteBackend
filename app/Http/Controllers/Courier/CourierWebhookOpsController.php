<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use App\Services\Courier\CourierAccountService;
use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\CourierWebhookEventService;
use App\Services\Courier\WordPressCourierForwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CourierWebhookOpsController extends Controller
{
    public function __construct(
        protected CourierShipmentService $shipmentService,
        protected CourierWebhookEventService $eventService,
        protected CourierForwardRetryService $retryService,
        protected CourierAccountService $accountService,
        protected WordPressCourierForwarder $forwarder
    ) {
    }

    public function webhookHealth(Request $request)
    {
        $partner = strtolower(trim((string) $request->query('partner', '')));
        $accessToken = $this->accountService->resolveAccessToken($request);

        $health = $this->eventService->healthSummary($accessToken?->id, $partner !== '' ? $partner : null);
        $health['pending_retries'] = $this->retryService->pendingCount($accessToken?->id);

        $latestShipment = $this->shipmentService->latestForAccessToken(
            $accessToken?->id,
            $partner !== '' ? $partner : null
        );

        $health = array_merge($health, $this->forwarder->probeReachability($latestShipment, $accessToken));

        return $this->successResponse($health);
    }

    public function webhookEvents(Request $request)
    {
        $partner = strtolower(trim((string) $request->query('partner', '')));
        $accessToken = $this->accountService->resolveAccessToken($request);
        $limit = max(1, min(50, (int) $request->query('limit', 20)));

        if ($partner !== '' && !in_array($partner, ['steadfast', 'pathao', 'redx'], true)) {
            return $this->errorResponse('Invalid courier partner.');
        }

        return $this->successResponse([
            'events' => $this->eventService->recentEvents(
                $accessToken?->id,
                $partner !== '' ? $partner : null,
                $limit
            ),
        ]);
    }

    public function backfillShipments(Request $request)
    {
        $partner = strtolower(trim((string) $request->input('partner', '')));

        if (!in_array($partner, ['steadfast', 'pathao', 'redx'], true)) {
            return $this->errorResponse('Invalid courier partner.');
        }

        $validator = Validator::make($request->all(), [
            'shipments' => 'required|array|max:500',
            'shipments.*.consignment_id' => 'nullable|string|max:128',
            'shipments.*.tracking_code' => 'nullable|string|max:128',
            'shipments.*.invoice' => 'nullable|string|max:128',
            'shipments.*.wc_order_id' => 'nullable|integer|min:1',
            'shipments.*.status' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $configuration = CourierConfiguration::query()
            ->where('user_id', Auth::id())
            ->where('slug', $partner)
            ->first();

        if (!$configuration) {
            return $this->errorResponse('Courier configuration not found for this partner.');
        }

        $result = $this->shipmentService->backfillShipments(
            $partner,
            $configuration,
            $request,
            $request->input('shipments', [])
        );

        return $this->successResponse($result, 'Shipment mappings backfilled.');
    }
}
