<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Services\PathaoCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PathaoController extends Controller
{
    protected PathaoCourierService $pathaoService;

    public function __construct(PathaoCourierService $pathaoService)
    {
        $this->pathaoService = $pathaoService;
    }

    private function resolveAuthConfig(Request $request)
    {
        return $this->pathaoService->getAuthConfig(
            (int) (Auth::id() ?? 0),
            $request->only(['api_key', 'secret_key', 'username', 'password', 'environment', 'courier_config_id'])
        );
    }

    private function resolveCatalogConfig(Request $request)
    {
        $config = $this->resolveAuthConfig($request);

        if (!$config) {
            return null;
        }

        return $this->applyEnvironmentToConfig($config, $request->input('environment'));
    }

    private function storeValidationRules(): array
    {
        return [
            'name' => 'required|string|min:3|max:50',
            'contact_name' => 'required|string|min:3|max:50',
            'contact_number' => 'required|string|regex:/^01[0-9]{9}$/',
            'address' => 'required|string|min:15|max:120',
            'city_id' => 'required|integer|min:1',
            'zone_id' => 'required|integer|min:1',
            'area_id' => 'required|integer|min:1',
        ];
    }

    public function checkBalance()
    {
        return $this->successResponse([
            'balance' => null,
            'balance_available' => false,
            'message' => 'Pathao does not expose a merchant balance API.',
        ]);
    }

    public function createOrder(Request $request)
    {
        $config = $this->pathaoService->getConfig(Auth::id());

        if (!$config) {
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), $this->orderRules());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $order = [
            'invoice' => $request->invoice,
            'recipient_name' => $request->recipient_name,
            'recipient_phone' => $request->recipient_phone,
            'recipient_address' => $request->recipient_address,
            'cod_amount' => $request->cod_amount,
            'note' => $request->note,
        ];

        $result = $this->pathaoService->createOrder($config, $order);

        if (!empty($result['error'])) {
            return $this->errorResponse($result['error']);
        }

        return $this->successResponse($result);
    }

    public function createBulkOrder(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            LogHelper::saveLog('pathao bulk configuration issue', 'not configured properly');
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $config = $this->applyPathaoOptionsToConfig($config, $request->input('pathao_options', []));

        $settings = is_array($config->settings) ? $config->settings : [];

        if (empty($settings['store_id'])) {
            return $this->errorResponse('Select a Pathao store when sending orders.');
        }

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|max:200',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'orders.*.recipient_address' => 'required|string|min:10',
            'orders.*.cod_amount' => 'required|numeric',
            'orders.*.recipient_city' => 'nullable|integer|min:1',
            'orders.*.recipient_zone' => 'nullable|integer|min:1',
            'orders.*.recipient_area' => 'nullable|integer|min:1',
            'pathao_options.recipient_city' => 'nullable|integer|min:1',
            'pathao_options.recipient_zone' => 'nullable|integer|min:1',
            'pathao_options.recipient_area' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            try {
                LogHelper::saveLog('pathao bulk validation error', json_encode($validator->messages()));
            } catch (\Throwable $th) {
            }

            return $this->validationErrorResponse($validator->messages());
        }

        $orders = $request->input('orders', []);
        $results = [];

        foreach ($orders as $order) {
            $results[] = $this->pathaoService->createOrder($config, is_array($order) ? $order : []);
        }

        return $this->successResponse($results);
    }

    public function checkStatus(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $consignmentId = $request->consignment_id;
        $status = $this->pathaoService->getOrderStatus($config, (string) $consignmentId);

        return $this->successResponse($status);
    }

    public function bulkCheckStatus(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $consignmentIds = $request->consignment_ids ?? [];
        $responseData = [];

        foreach ($consignmentIds as $id) {
            $responseData[$id] = $this->pathaoService->getOrderStatus($config, (string) $id);
        }

        return $this->successResponse($responseData);
    }

    public function getStores(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $includeStoreId = (int) $request->input('store_id', 0);

        $stores = $this->pathaoService->getStores(
            $config,
            3,
            100,
            $includeStoreId > 0 ? $includeStoreId : null
        );

        return $this->successResponse($stores);
    }

    public function getCities(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        return $this->successResponse($this->pathaoService->getCities($config));
    }

    public function getZones(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $validator = Validator::make($request->all(), [
            'city_id' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        return $this->successResponse(
            $this->pathaoService->getZones($config, (int) $request->city_id)
        );
    }

    public function getZonesByCity(Request $request, $cityId)
    {
        $request->merge(['city_id' => $cityId]);

        return $this->getZones($request);
    }

    public function getAreas(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        return $this->successResponse(
            $this->pathaoService->getAreas($config, (int) $request->zone_id)
        );
    }

    public function getAreasByZone(Request $request, $zoneId)
    {
        $request->merge(['zone_id' => $zoneId]);

        return $this->getAreas($request);
    }

    public function createStore(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $validator = Validator::make($request->all(), $this->storeValidationRules());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $result = $this->pathaoService->createStore($config, $request->only([
            'name',
            'contact_name',
            'contact_number',
            'address',
            'city_id',
            'zone_id',
            'area_id',
        ]));

        if (!$result['success']) {
            return $this->errorResponse(
                $result['message'],
                400,
                $result['errors'] ?? null
            );
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    public function updateStore(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $validator = Validator::make($request->all(), array_merge(
            ['store_id' => 'required|integer|min:1'],
            $this->storeValidationRules()
        ));

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $result = $this->pathaoService->updateStore(
            $config,
            (int) $request->input('store_id'),
            $request->only([
                'name',
                'contact_name',
                'contact_number',
                'address',
                'city_id',
                'zone_id',
                'area_id',
            ])
        );

        if (!$result['success']) {
            return $this->errorResponse(
                $result['message'],
                400,
                $result['errors'] ?? null
            );
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    public function deleteStore(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $result = $this->pathaoService->deleteStore($config, (int) $request->input('store_id'));

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    public function pricePlan(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer|min:1',
            'recipient_city' => 'required|integer|min:1',
            'recipient_zone' => 'required|integer|min:1',
            'delivery_type' => 'nullable|integer',
            'item_type' => 'nullable|integer',
            'item_weight' => 'nullable|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $result = $this->pathaoService->calculatePrice($config, $request->all());

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    public function testConnection(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $result = $this->pathaoService->testConnection($config);

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse(null, $result['message']);
    }

    public function resetToken(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $result = $this->pathaoService->resetToken($config);

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse(null, $result['message']);
    }

    public function merchantInfo(Request $request)
    {
        $config = $this->resolveCatalogConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao Client ID and Secret first.');
        }

        $result = $this->pathaoService->getMerchantInfo($config);

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    private function applyPathaoOptionsToConfig($config, $pathaoOptions)
    {
        if (!is_array($pathaoOptions) || $pathaoOptions === []) {
            return $config;
        }

        $settings = is_array($config->settings) ? $config->settings : [];

        foreach ([
            'store_id',
            'delivery_type',
            'item_type',
            'item_quantity',
            'item_weight',
            'recipient_city',
            'recipient_zone',
            'recipient_area',
        ] as $key) {
            if (array_key_exists($key, $pathaoOptions) && $pathaoOptions[$key] !== '' && $pathaoOptions[$key] !== null) {
                $settings[$key] = $pathaoOptions[$key];
            }
        }

        $config->settings = $settings;

        return $config;
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

    private function orderRules(): array
    {
        return [
            'invoice' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/|max:255',
            'recipient_name' => 'required|string|max:100',
            'recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'recipient_address' => 'required|string|max:250|min:10',
            'cod_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ];
    }
}
