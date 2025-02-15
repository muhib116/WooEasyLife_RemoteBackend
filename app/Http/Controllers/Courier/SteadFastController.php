<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\LogHelper;
use App\Models\CourierConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SteadFastController extends Controller
{
    protected $baseUrl;
    // protected $apiKey;
    // protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://portal.packzy.com/api/v1';
        // $this->apiKey = 'j2a4jnjre3fv87rg41yyolpmlzu7os80';
        // $this->secretKey = 'rmxck4fxysvp8u3nwjcfgm3t';
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
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $consignmentIds = $request->consignment_ids ?? [];
        $invoiceIds = $request->invoice_ids ?? [];

        $response_data = [];

        if (count($consignmentIds)) {
            foreach ($consignmentIds as $id) {
                $response = Http::withHeaders([
                    'Api-Key' => $config->api_key,
                    'Secret-Key' => $config->secret_key,
                    'Content-Type' => 'application/json',
                ])->get($this->baseUrl . '/status_by_cid/' . $id);
                $status = '';
                try {
                    $jsonResponse = $response->json();
                    if (@$jsonResponse['status'] == '200') {
                        $status = @$jsonResponse['delivery_status'];
                    }
                } catch (\Throwable $th) {
                }
                $response_data[$id] = $status;
            }
        } else if (count($invoiceIds)) {
            foreach ($consignmentIds as $id) {
                $response = Http::withHeaders([
                    'Api-Key' => $config->api_key,
                    'Secret-Key' => $config->secret_key,
                    'Content-Type' => 'application/json',
                ])->get($this->baseUrl . '/status_by_invoice/' . $id);
                $status = '';
                try {
                    $jsonResponse = $response->json();
                    if (@$jsonResponse['status'] == '200') {
                        $status = @$jsonResponse['delivery_status'];
                    }
                } catch (\Throwable $th) {
                }
                $response_data[$id] = $status;
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

        $steadfastBalance = 0;

        try {
            $config = $this->getConfig();
            $response = Http::withHeaders([
                'Api-Key' => $config->api_key,
                'Secret-Key' => $config->secret_key,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/get_balance');
            $jsonResponse = $response->json();
            $steadfastBalance = $jsonResponse['current_balance'];
        } catch (\Throwable $th) {
            //throw $th;
        }

        $responseData = [
            'steadfast' => [
                'logo' => asset('images/steadfast.png'),
                'balance' => $steadfastBalance
            ],
            'paperfly' => [
                'logo' => asset('images/paperfly.png'),
                'balance' => 0
            ],
            'redx' => [
                'logo' => asset('images/redx.png'),
                'balance' => 0
            ],
            'pathao' => [
                'logo' => asset('images/pathao.png'),
                'balance' => 0
            ],
            'total' => $steadfastBalance,
        ];

        return $this->successResponse($responseData);
    }

    public function createOrder(Request $request)
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'invoice' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/|max:255,invoice',
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
                // try {
                // } catch (\Throwable $th) {}
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
}
