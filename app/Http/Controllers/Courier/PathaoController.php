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
            $request->only(['api_key', 'secret_key', 'username', 'password', 'environment'])
        );
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
        $config = $this->pathaoService->getConfig(Auth::id());

        if (!$config) {
            LogHelper::saveLog('pathao bulk configuration issue', 'not configured properly');
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|max:200',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'orders.*.recipient_address' => 'required|string|min:10',
            'orders.*.cod_amount' => 'required|numeric',
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
            $results[] = $this->pathaoService->createOrder($config, [
                'invoice' => $order['invoice'] ?? '',
                'recipient_name' => $order['recipient_name'] ?? '',
                'recipient_phone' => $order['recipient_phone'] ?? '',
                'recipient_address' => $order['recipient_address'] ?? '',
                'cod_amount' => $order['cod_amount'] ?? 0,
                'note' => $order['note'] ?? null,
            ]);
        }

        return $this->successResponse($results);
    }

    public function checkStatus(Request $request)
    {
        $config = $this->pathaoService->getConfig(Auth::id());

        if (!$config) {
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $consignmentId = $request->consignment_id;
        $status = $this->pathaoService->getOrderStatus($config, (string) $consignmentId);

        return $this->successResponse($status);
    }

    public function bulkCheckStatus(Request $request)
    {
        $config = $this->pathaoService->getConfig(Auth::id());

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
        $config = $this->resolveAuthConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao credentials first (Client ID, Secret, login email, password).');
        }

        $stores = $this->pathaoService->getStores($config);

        return $this->successResponse($stores);
    }

    public function getCities(Request $request)
    {
        $config = $this->resolveAuthConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao credentials first (Client ID, Secret, login email, password).');
        }

        return $this->successResponse($this->pathaoService->getCities($config));
    }

    public function getZones(Request $request)
    {
        $config = $this->resolveAuthConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao credentials first (Client ID, Secret, login email, password).');
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

    public function getAreas(Request $request)
    {
        $config = $this->resolveAuthConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao credentials first (Client ID, Secret, login email, password).');
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

    public function createStore(Request $request)
    {
        $config = $this->resolveAuthConfig($request);

        if (!$config) {
            return $this->errorResponse('Save Pathao credentials first (Client ID, Secret, login email, password).');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'contact_name' => 'required|string|max:100',
            'contact_number' => 'required|string',
            'address' => 'required|string|min:10',
            'city_id' => 'required|integer|min:1',
            'zone_id' => 'required|integer|min:1',
            'area_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $result = $this->pathaoService->createStore($config, $request->all());

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse($result['data'], $result['message']);
    }

    public function pricePlan(Request $request)
    {
        $config = $this->pathaoService->getConfig(Auth::id());

        if (!$config) {
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        $result = $this->pathaoService->calculatePrice($config, $request->all());

        if (!$result['success']) {
            return $this->errorResponse($result['message']);
        }

        return $this->successResponse($result['data'], $result['message']);
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
