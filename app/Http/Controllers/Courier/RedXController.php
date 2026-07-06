<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Services\Courier\CourierAccountService;
use App\Services\Courier\CourierShipmentService;
use App\Services\RedXCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RedXController extends Controller
{
    protected RedXCourierService $redxService;

    public function __construct(
        RedXCourierService $redxService,
        protected CourierShipmentService $shipmentService,
        protected CourierAccountService $courierAccountService
    ) {
        $this->redxService = $redxService;
    }

    public function testConnection(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        $result = $this->redxService->testConnection($config);

        if (!$result['ok']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse($result['data'] ?? null, $result['message']);
    }

    public function getArea(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        try {
            return $this->successResponse($this->redxService->getAreas($config));
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage() ?: 'There is some issue to get areas.');
        }
    }

    public function getPickupStores(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        try {
            return $this->successResponse($this->redxService->getPickupStores($config));
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage() ?: 'Could not load RedX pickup stores.');
        }
    }

    public function chargeCalculator(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'delivery_area_id' => 'required|integer|min:1',
            'weight' => 'required|numeric|min:1',
            'cash_collection_amount' => 'nullable|numeric|min:0',
            'pickup_area_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        try {
            return $this->successResponse($this->redxService->calculateCharge($config, $request->all()));
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage() ?: 'Could not calculate RedX delivery charge.');
        }
    }

    public function createOrder(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'delivery_area' => 'required|string|max:255',
            'delivery_area_id' => 'required|integer|min:1',
            'customer_address' => 'required|string|max:500',
            'cash_collection_amount' => 'required|numeric|min:0',
            'parcel_weight' => 'required|numeric|min:1',
            'merchant_invoice_id' => 'nullable|string|max:255',
            'instruction' => 'nullable|string|max:1000',
            'value' => 'required|numeric|min:0',
            'pickup_store_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $order = [
            'invoice' => $request->merchant_invoice_id,
            'recipient_name' => $request->customer_name,
            'recipient_phone' => $request->customer_phone,
            'recipient_address' => $request->customer_address,
            'cod_amount' => $request->cash_collection_amount,
            'parcel_weight' => $request->parcel_weight,
            'note' => $request->instruction,
            'value' => $request->value,
            'delivery_area' => $request->delivery_area,
            'delivery_area_id' => $request->delivery_area_id,
            'pickup_store_id' => $request->pickup_store_id,
        ];

        $result = $this->redxService->createOrder($config, $order);

        if (!empty($result['error'])) {
            return $this->errorResponse($result['error'], $result);
        }

        return $this->successResponse([
            'tracking_id' => $result['consignment_id'],
            'consignment_id' => $result['consignment_id'],
            'status' => $result['status'],
        ]);
    }

    public function createBulkOrder(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        $settings = $this->redxService->normalizeSettings($config->settings);
        $redxOptions = is_array($request->input('redx_options')) ? $request->input('redx_options') : [];

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|max:200',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'orders.*.recipient_address' => 'required|string|min:10',
            'orders.*.cod_amount' => 'required|numeric|min:0',
            'orders.*.value' => 'nullable|numeric|min:0',
            'orders.*.delivery_area_id' => 'nullable|integer|min:1',
            'orders.*.delivery_area' => 'nullable|string|max:255',
            'redx_options.delivery_area_id' => 'nullable|integer|min:1',
            'redx_options.delivery_area' => 'nullable|string|max:255',
            'redx_options.pickup_store_id' => 'nullable|integer|min:1',
            'redx_options.parcel_weight' => 'nullable|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $defaultAreaId = (int) ($redxOptions['delivery_area_id'] ?? $settings['delivery_area_id'] ?? 0);
        $defaultAreaName = (string) ($redxOptions['delivery_area'] ?? $settings['delivery_area'] ?? '');

        if ($defaultAreaId <= 0) {
            return $this->errorResponse('Select a RedX delivery area when sending orders.');
        }

        $orders = $request->input('orders', []);
        $results = [];

        foreach ($orders as $order) {
            $row = is_array($order) ? $order : [];
            $row['delivery_area_id'] = (int) ($row['delivery_area_id'] ?? $defaultAreaId);
            $row['delivery_area'] = (string) ($row['delivery_area'] ?? $defaultAreaName);

            if (empty($row['value'])) {
                $row['value'] = $row['cod_amount'] ?? 0;
            }

            $results[] = $this->redxService->createOrder($config, $row, $redxOptions);
        }

        $results = $this->shipmentService->recordSuccessfulOrders('redx', $config, $results, $request);

        return $this->successResponse($results);
    }

    public function trackParcel(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'tracking_id' => 'required_without:track_id|string|max:64',
            'track_id' => 'required_without:tracking_id|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $trackingId = trim((string) ($request->input('tracking_id') ?: $request->input('track_id')));
        $result = $this->redxService->getParcelTrackingHistory($config, $trackingId);

        if (!empty($result['error'])) {
            return $this->errorResponse($result['error'], $result);
        }

        return $this->successResponse($result);
    }

    public function bulkTrackStatus(Request $request)
    {
        $trackingIds = $request->consignment_ids ?? $request->track_ids ?? [];

        if (!is_array($trackingIds) || empty($trackingIds)) {
            return $this->validationErrorResponse([
                'consignment_ids' => ['At least one tracking ID is required.'],
            ]);
        }

        $responseData = [];
        $catalogConfig = $this->resolveCatalogConfig($request);
        $environment = $catalogConfig
            ? $this->courierAccountService->environmentFromConfig($catalogConfig)
            : null;
        $groups = $this->shipmentService->groupConsignmentsByAccount('redx', $trackingIds, $environment);

        if (empty($groups)) {
            $groups = [0 => $trackingIds];
        }

        foreach ($groups as $accountId => $ids) {
            $config = $this->courierAccountService->configurationForAccount(
                (int) $accountId,
                (int) (Auth::id() ?? 0),
                'redx'
            );

            if (!$config) {
                $config = $catalogConfig;
            }

            if (!$config) {
                continue;
            }

            $responseData += $this->redxService->getTrackingStatuses($config, $ids);
        }

        if ($responseData === [] && $catalogConfig === null) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        return $this->successResponse($responseData);
    }

    public function checkBalance()
    {
        $config = $this->redxService->getConfig(Auth::id());

        if (!$config) {
            return $this->errorResponse('The RedX settings are not configured properly.');
        }

        return $this->successResponse([
            'balance' => null,
            'balance_available' => false,
            'message' => 'RedX does not expose a merchant balance API.',
        ]);
    }

    private function resolveCatalogConfig(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (!$config) {
            return null;
        }

        return $this->applyEnvironmentToConfig($config, $request->input('environment'));
    }

    private function resolveConfig(Request $request)
    {
        $override = [];

        if ($request->filled('secret_key')) {
            $override['secret_key'] = $request->input('secret_key');
        }

        if ($request->filled('api_key')) {
            $override['api_key'] = $request->input('api_key');
        }

        if ($request->filled('environment')) {
            $override['environment'] = $request->input('environment') === 'live' ? 'live' : 'sandbox';
        }

        if ($request->filled('courier_config_id')) {
            $override['courier_config_id'] = (int) $request->input('courier_config_id');
        }

        if ($override) {
            return $this->redxService->getAuthConfig(Auth::id(), $override);
        }

        return $this->redxService->getConfig(Auth::id());
    }

    private function applyEnvironmentToConfig($config, $environment)
    {
        if ($environment === null || $environment === '') {
            return $config;
        }

        $settings = is_array($config->settings) ? $config->settings : [];
        $previousEnvironment = $settings['environment'] ?? 'sandbox';
        $settings['environment'] = $environment === 'live' ? 'live' : 'sandbox';

        if ($previousEnvironment !== $settings['environment']) {
            unset($settings['access_token'], $settings['refresh_token'], $settings['expires_at']);
        }

        $config->settings = $settings;

        return $config;
    }
}
