<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Models\CourierConfiguration;
use App\Services\Courier\CourierAccountService;
use App\Services\Courier\CourierLogoUrl;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\SteadfastNotificationsService;
use App\Services\Courier\SteadfastParcelNotesService;
use App\Services\Courier\SteadfastReturnRequestsService;
use App\Services\Courier\SteadfastStatusBatchService;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\MerchantPackageFeatureGate;
use App\Services\PathaoCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SteadFastController extends Controller
{
    protected $baseUrl;
    // protected $apiKey;
    // protected $secretKey;

    public function __construct(
        protected CourierShipmentService $shipmentService,
        protected CourierAccountService $courierAccountService,
        protected SteadfastStatusBatchService $steadfastStatusBatchService,
        protected SteadfastParcelNotesService $parcelNotesService,
        protected SteadfastReturnRequestsService $returnRequestsService,
        protected SteadfastNotificationsService $notificationsService,
        protected MerchantSteadfastFraudCredentialResolver $steadfastPortalCredentials,
        protected MerchantPackageFeatureGate $packageFeatureGate,
    ) {
        $this->baseUrl = 'https://portal.packzy.com/api/v1';
    }

    private function getConfig()
    {
        $config = CourierConfiguration::where('user_id', Auth::id())
            ->where('slug', 'steadfast')
            ->first();

        if (!$config || !$config->api_key || !$config->secret_key) {
            return false;
        }

        return $config;
    }

    public function checkStatus(Request $request)
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $consignmentId = $request->consignment_id;

        $response = Http::withHeaders([
            'Api-Key' => $config->api_key,
            'Secret-Key' => $config->secret_key,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/status_by_cid/' . $consignmentId);

        $status = '';
        try {
            $jsonResponse = $response->json();
            if (@$jsonResponse['status'] == '200') {
                $status = @$jsonResponse['delivery_status'];
            }
        } catch (\Throwable $th) {
            return $this->errorResponse('There\'s an error to get status.');
        }
        return $this->successResponse($status);
    }

    public function bulkCheckStatus(Request $request)
    {
        $consignmentIds = $request->consignment_ids ?? [];
        $invoiceIds = $request->invoice_ids ?? [];
        $response_data = [];
        $config = $this->getConfig();
        $environment = $config
            ? $this->courierAccountService->environmentFromConfig($config)
            : null;

        $groups = $this->shipmentService->groupConsignmentsByAccount(
            'steadfast',
            is_array($consignmentIds) ? $consignmentIds : [],
            $environment
        );

        if (empty($groups) && count($consignmentIds)) {
            $groups = [0 => $consignmentIds];
        }

        foreach ($groups as $accountId => $ids) {
            $config = $this->courierAccountService->configurationForAccount(
                (int) $accountId,
                (int) (Auth::id() ?? 0),
                'steadfast'
            );

            if (!$config) {
                $config = $this->getConfig();
            }

            if (!$config) {
                continue;
            }

            $response_data += $this->steadfastStatusBatchService->fetchStatuses(
                    $config,
                    is_array($ids) ? $ids : [],
                    [],
                );
        }

        if (count($invoiceIds)) {
            $config = $this->getConfig();
            if ($config) {
                $response_data += $this->steadfastStatusBatchService->fetchStatuses(
                        $config,
                        [],
                        is_array($invoiceIds) ? $invoiceIds : [],
                    );
            }
        }

        return $this->successResponse($response_data);
    }

    public function checkBalance()
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }
        try {
            $response = Http::withHeaders([
                'Api-Key' => $config->api_key,
                'Secret-Key' => $config->secret_key,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/get_balance');
            $statusCode = $response->status();
            $jsonResponse = $response->json();
            if ($statusCode == 200) {
                return $this->successResponse([
                    'balance' => $jsonResponse['current_balance']
                ]);
            } else {
                return $this->errorResponse(
                    'Opps! Something went wrong to get balance.',
                    $statusCode
                );
            }
        } catch (\Throwable $th) {
            return $this->errorResponse('Opps! Something went wrong to get balance.');
        }
    }

    public function checkCourierBalance(Request $request)
    {
        $steadfastBalance = null;
        $steadfastConfigured = false;
        $pathaoConfigured = false;

        try {
            $config = $this->getConfig();
            if ($config) {
                $steadfastConfigured = true;
                $response = Http::withHeaders([
                    'Api-Key' => $config->api_key,
                    'Secret-Key' => $config->secret_key,
                    'Content-Type' => 'application/json',
                ])->get($this->baseUrl . '/get_balance');
                $jsonResponse = $response->json();
                $steadfastBalance = $jsonResponse['current_balance'] ?? 0;
            }
        } catch (\Throwable $th) {
        }

        try {
            $pathaoService = app(PathaoCourierService::class);
            $pathaoConfig = $pathaoService->getAuthConfig((int) (Auth::id() ?? 0));

            if ($pathaoConfig && $pathaoConfig->is_active) {
                $pathaoConfigured = true;
            }
        } catch (\Throwable $th) {
        }

        $responseData = [
            'total' => $steadfastConfigured ? (float) ($steadfastBalance ?? 0) : 0,
            'total_includes_pathao' => false,
        ];

        if ($steadfastConfigured) {
            $responseData['steadfast'] = [
                'logo' => CourierLogoUrl::forSlug('steadfast'),
                'balance' => $steadfastBalance ?? 0,
                'balance_available' => true,
            ];
        }

        if ($pathaoConfigured) {
            $responseData['pathao'] = [
                'logo' => CourierLogoUrl::forSlug('pathao'),
                'balance' => null,
                'balance_available' => false,
                'message' => 'Pathao does not expose a merchant balance API.',
            ];
        }

        return $this->successResponse($responseData);
    }

    public function createOrder(Request $request)
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'invoice' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/|max:255',
            'recipient_name' => 'required|string|max:100',
            'recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'recipient_address' => 'required|string|max:250',
            'cod_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $data = [
            'invoice' => $request->invoice,
            'recipient_name' => $request->recipient_name,
            'recipient_phone' => $request->recipient_phone,
            'recipient_address' => $request->recipient_address,
            'cod_amount' => $request->cod_amount,
            'note' => $request->note,
        ];

        $response = $this->placeOrder($data);

        if (@$response->status == 200 && @$response->consignment) {
            return $this->successResponse($response->consignment, $response->message);
        }

        return $this->errorResponse('An issue occurred while processing the order.');
    }

    public function createBulkOrder(Request $request)
    {
        $config = $this->getConfig();

        if (!$config) {
            LogHelper::saveLog('steadfast bulk configuration issue', 'not configured properly');
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'orders.*.recipient_address' => 'required|string',
            'orders.*.cod_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            try {
                LogHelper::saveLog('steadfast bulk', 'issue on validate');
                LogHelper::saveLog('steadfast bulk request was', json_encode($request->all()));
                LogHelper::saveLog('steadfast bulk validation error', 'Validation Issue'. json_encode($validator->messages()));
            } catch (\Throwable $th) {
                //throw $th;
            }
            return $this->validationErrorResponse($validator->messages());
        }

        $orders = $request->input('orders');

        $data = array_map(function ($order) {
            return [
                'invoice' => $order['invoice'],
                'recipient_name' => $order['recipient_name'],
                'recipient_phone' => $order['recipient_phone'],
                'recipient_address' => $order['recipient_address'],
                'cod_amount' => $order['cod_amount'],
                'note' => $order['note'] ?? null,
            ];
        }, $orders);

        // return $this->successResponse($data);

        try {
            $response = Http::withHeaders([
                'Api-Key' => $config->api_key,
                'Secret-Key' => $config->secret_key,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/create_order/bulk-order', [
                'data' => json_encode($data)
            ]);

            $statusCode = $response->status();

            if ($statusCode == 200) {
                $data = $response->json();
                $data = array_map(function ($order) {
                    $order['created_at'] = now();
                    $order['updated_at'] = now();
                    return $order;
                }, $data['data'] ?? []);

                $data = $this->shipmentService->recordSuccessfulOrders('steadfast', $config, $data, $request);

                return $this->successResponse($data);
            } else {

                try {
                    LogHelper::saveLog('steadfast status not 200 and response was', $response->json());
                } catch (\Throwable $th) {
                    //throw $th;
                }
                
                LogHelper::saveLog('steadfast bulk', 'The SteadFast configuration is not valid.');
                $errorMessage = $response->getBody()->getContents();
                if ($statusCode == 401) {
                    $errorMessage = 'The SteadFast configuration is not valid.';
                }
                return $this->errorResponse(
                    $errorMessage,
                    $statusCode,
                );
            }
        } catch (\Throwable $th) {
            LogHelper::saveLog('steadfast bulk bulk add error', $th->getMessage());
            //throw $th;
            // return $this->errorResponse("There's an error while creating error");
            return $this->errorResponse($th->getMessage());
        }
    }

    private function placeOrder($data)
    {
        $config = $this->getConfig();
        $response = Http::withHeaders([
            'Api-Key' => $config->api_key,
            'Secret-Key' => $config->secret_key,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/create_order', $data);

        return $response->json();
    }

    // private function bulkCreateOrders($data)
    // {
    //     $config = $this->getConfig();

    //     $response = Http::withHeaders([
    //         'Api-Key' => $config->api_key,
    //         'Secret-Key' => $config->secret_key,
    //         'Content-Type' => 'application/json',
    //     ])->post($this->baseUrl . '/create_order/bulk-order', [
    //         'data' => json_encode($data)
    //     ]);

    //     return $response->json();
    // }

    // private function checkDeliveryStatusByConsignmentId($id)
    // {
    //     $response = Http::withHeaders([
    //         'Api-Key' => $this->apiKey,
    //         'Secret-Key' => $this->secretKey,
    //         'Content-Type' => 'application/json',
    //     ])->get($this->baseUrl . '/status_by_cid/' . $id);

    //     return $response->json();
    // }


    // private function checkDeliveryStatusByInvoiceId($id)
    // {
    //     $response = Http::withHeaders([
    //         'Api-Key' => $this->apiKey,
    //         'Secret-Key' => $this->secretKey,
    //         'Content-Type' => 'application/json',
    //     ])->get($this->baseUrl . '/status_by_invoice/' . $id);

    //     return $response->json();
    // }

    // private function checkDeliveryStatusByTrackingCode($id)
    // {
    //     $response = Http::withHeaders([
    //         'Api-Key' => $this->apiKey,
    //         'Secret-Key' => $this->secretKey,
    //         'Content-Type' => 'application/json',
    //     ])->get($this->baseUrl . '/status_by_trackingcode/' . $id);

    //     return $response->json();
    // }

    public function parcelNotes(Request $request)
    {
        if ($denied = $this->denyUnlessParcelNoteHistoryEnabled($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'consignment_id' => ['required', 'string', 'regex:/^\d{4,20}$/'],
            'tracking_code' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $credentials = $this->steadfastPortalCredentials->resolveFromCurrentRequest();
        if ($credentials === null) {
            return $this->errorResponse(
                'Steadfast portal username/password are not configured.',
                422
            );
        }

        try {
            $data = $this->parcelNotesService->fetchNotes(
                (string) $request->input('consignment_id'),
                $credentials,
                $request->input('tracking_code')
            );

            return $this->successResponse($data);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast parcel notes fetch failed', $th->getMessage());

            return $this->errorResponse($th->getMessage() ?: 'Unable to fetch Steadfast parcel notes.');
        }
    }

    public function updateParcelNote(Request $request)
    {
        if ($denied = $this->denyUnlessParcelNoteHistoryEnabled($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'consignment_id' => ['required', 'string', 'regex:/^\d{4,20}$/'],
            'note' => 'nullable|string|max:500',
            'cus_address' => 'nullable|string|max:500',
            'cod_amount' => 'nullable|numeric|min:0|max:9999999',
            'customer_name' => 'nullable|string|max:191',
            'customer_phone' => 'nullable|string|max:32',
            'recipient_name' => 'nullable|string|max:191',
            'recipient_phone' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $note = trim((string) $request->input('note', ''));
        $hasAddress = $request->exists('cus_address');
        $hasCod = $request->exists('cod_amount');
        $customerName = trim((string) ($request->input('customer_name') ?? $request->input('recipient_name') ?? ''));
        $customerPhone = trim((string) ($request->input('customer_phone') ?? $request->input('recipient_phone') ?? ''));
        $hasCustomerName = $request->exists('customer_name') || $request->exists('recipient_name');
        $hasCustomerPhone = $request->exists('customer_phone') || $request->exists('recipient_phone');

        if ($note === '' && ! $hasAddress && ! $hasCod && ! $hasCustomerName && ! $hasCustomerPhone) {
            return $this->errorResponse('Provide a note, address, COD amount, or customer details to update.', 422);
        }

        $credentials = $this->steadfastPortalCredentials->resolveFromCurrentRequest();
        if ($credentials === null) {
            return $this->errorResponse(
                'Steadfast portal username/password are not configured.',
                422
            );
        }

        $overrides = [];
        if ($hasAddress) {
            $overrides['cus_address'] = (string) $request->input('cus_address');
        }
        if ($hasCod) {
            $overrides['cod_amount'] = $request->input('cod_amount');
        }
        if ($hasCustomerName) {
            $overrides['customer_name'] = $customerName;
        }
        if ($hasCustomerPhone) {
            $overrides['customer_phone'] = $customerPhone;
        }

        try {
            $data = $this->parcelNotesService->updateMerchantNote(
                (string) $request->input('consignment_id'),
                $note,
                $credentials,
                $overrides
            );

            return $this->successResponse($data, 'Parcel updated');
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast parcel note update failed', $th->getMessage());

            return $this->errorResponse($th->getMessage() ?: 'Unable to update Steadfast parcel note.');
        }
    }

    public function listNotifications(Request $request)
    {
        if ($denied = $this->denyUnlessCourierAutomationEnabled($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'cursor' => ['nullable', 'string', 'max:2000'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $credentials = $this->resolvePortalCredentialsForRequest($request);
        if ($credentials === null) {
            return $this->errorResponse(
                'Steadfast portal username/password are not configured. Add them in Config → Courier → Steadfast.',
                422
            );
        }

        try {
            $data = $this->notificationsService->list(
                $credentials,
                $request->input('cursor')
            );

            return $this->successResponse($data);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast notifications fetch failed', $th->getMessage());

            return $this->errorResponse($th->getMessage() ?: 'Unable to fetch Steadfast notifications.');
        }
    }

    /**
     * Prefer explicit request credentials (plugin local vault), then saved courier config.
     *
     * @return array{username: string, password: string}|null
     */
    private function resolvePortalCredentialsForRequest(Request $request): ?array
    {
        $fromRequest = $this->steadfastPortalCredentials->credentialsFromSettings([
            'username' => (string) $request->input('username', ''),
            'password' => (string) $request->input('password', ''),
        ]);
        if ($fromRequest !== null) {
            return $fromRequest;
        }

        $stored = $this->steadfastPortalCredentials->resolveFromCurrentRequest();
        if ($stored === null) {
            return null;
        }

        // Allow partial overrides (e.g. password from plugin + username from hub).
        $username = trim((string) $request->input('username', ''));
        $password = trim((string) $request->input('password', ''));
        if ($username === '' && $password === '') {
            return $stored;
        }

        return $this->steadfastPortalCredentials->credentialsFromSettings([
            'username' => $username !== '' ? $username : $stored['username'],
            'password' => $password !== '' ? $password : $stored['password'],
        ]);
    }

    public function createReturnRequest(Request $request)
    {
        if ($denied = $this->denyUnlessCourierAutomationEnabled($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'consignment_id' => ['required', 'string', 'regex:/^\d{4,20}$/'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'tracking_code' => ['nullable', 'string', 'max:64'],
            'invoice' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $config = $this->getConfig();
        if (! $config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.', 422);
        }

        $portalCredentials = $this->steadfastPortalCredentials->resolveFromCurrentRequest();

        try {
            $data = $this->returnRequestsService->create(
                (string) $request->input('consignment_id'),
                (string) $request->input('reason'),
                [
                    'api_key' => (string) $config->api_key,
                    'secret_key' => (string) $config->secret_key,
                ],
                $portalCredentials,
                $request->input('tracking_code'),
                $request->input('invoice')
            );

            return $this->successResponse($data, 'Return request created');
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast return request create failed', $th->getMessage());

            return $this->errorResponse($th->getMessage() ?: 'Unable to create Steadfast return request.');
        }
    }

    public function listReturnRequests(Request $request)
    {
        if ($denied = $this->denyUnlessCourierAutomationEnabled($request)) {
            return $denied;
        }

        // Portal cancel-requests pagination can take well over PHP's default 30s.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('max_execution_time', '300');
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'string', 'max:32'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'mode' => ['nullable', 'string', 'in:quick,full'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $config = $this->getConfig();
        if (! $config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.', 422);
        }

        $portalCredentials = $this->steadfastPortalCredentials->resolveFromCurrentRequest();

        try {
            $data = $this->returnRequestsService->list(
                [
                    'api_key' => (string) $config->api_key,
                    'secret_key' => (string) $config->secret_key,
                ],
                $portalCredentials,
                $request->input('status'),
                $request->input('date'),
                $request->input('mode', 'full')
            );

            return $this->successResponse($data);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast return request list failed', $th->getMessage());

            return $this->errorResponse($th->getMessage() ?: 'Unable to list Steadfast return requests.');
        }
    }

    public function updateReturnRequestStatus(Request $request)
    {
        if ($denied = $this->denyUnlessCourierAutomationEnabled($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'action' => ['required', 'string', 'in:confirm_cancel,request_resend'],
            'consignment_id' => ['required', 'string', 'regex:/^\d{4,20}$/'],
            'id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $portalCredentials = $this->steadfastPortalCredentials->resolveFromCurrentRequest();
        if ($portalCredentials === null) {
            return $this->errorResponse(
                'Steadfast portal username/password are not configured. Add them in Config → Courier → Steadfast to confirm cancel or ask to resend.',
                422
            );
        }

        try {
            $data = $this->returnRequestsService->updateStatus(
                (string) $request->input('action'),
                $portalCredentials,
                (string) $request->input('consignment_id'),
                $request->input('id')
            );

            return $this->successResponse($data, 'Return request status updated');
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast return request status update failed', $th->getMessage());

            return $this->errorResponse($th->getMessage() ?: 'Unable to update Steadfast return request status.');
        }
    }

    private function denyUnlessCourierAutomationEnabled(Request $request)
    {
        if ($this->packageFeatureGate->hasFromRequest($request, 'courier_automation')) {
            return null;
        }

        return $this->errorResponse(
            'Courier automation is not included in your current plan.',
            403
        );
    }

    private function denyUnlessParcelNoteHistoryEnabled(Request $request)
    {
        if ($this->packageFeatureGate->hasFromRequest($request, 'parcel_note_history')
            || $this->packageFeatureGate->hasFromRequest($request, 'courier_automation')
        ) {
            return null;
        }

        return $this->errorResponse(
            'Parcel notes or courier automation is not included in your current plan.',
            403
        );
    }
}