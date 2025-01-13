<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use Codeboxr\RedxCourier\Facade\RedxCourier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class RedXController extends Controller
{
    protected $baseUrl = 'https://sandbox.redx.com.bd/v1.0.0-beta';
    protected $apiKey;
    protected $secretKey;
    // API-ACCESS-TOKEN = Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI5MTY5MDMiLCJpYXQiOjE3MzY3NzA0MjYsImlzcyI6ImhuV1FraTdYZWswb21ObDhJaXg2SmZNMW9pWjNURWxvIiwic2hvcF9pZCI6OTE2OTAzLCJ1c2VyX2lkIjo5NDM0MDA0fQ.2VSeFA5TxsgJPUzL-Fy0Bt3tNnD1V_CY-cJeYPmfkWc
    public function __construct()
    {
        // $this->baseUrl = 'https://portal.packzy.com/api/v1';
        $this->apiKey = 'j2a4jnjre3fv87rg41yyolpmlzu7os80';
        $this->secretKey = 'rmxck4fxysvp8u3nwjcfgm3t';
    }

    public function getArea()
    {
        return RedxCourier::area()->list();
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

    public function checkBalance()
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $response = Http::withHeaders([
            'Api-Key' => $config->api_key,
            'Secret-Key' => $config->secret_key,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/get_balance');

        try {
            $jsonResponse = $response->json();
            return $this->successResponse([
                'balance' => $jsonResponse['current_balance']
            ]);
        } catch (\Throwable $th) {
            return $this->errorResponse('Opps! Something went wrong to get balance.');
        }
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
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|digits:11|regex:/^01[0-9]{9}$/',
            'orders.*.recipient_address' => 'required|string',
            'orders.*.cod_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
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

        return $this->successResponse($data);

        // $response = $this->bulkCreateOrders($data);

        // return $this->successResponse($response);
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

    private function bulkCreateOrders($data)
    {
        $config = $this->getConfig();

        $response = Http::withHeaders([
            'Api-Key' => $config->api_key,
            'Secret-Key' => $config->secret_key,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/create_order/bulk-order', [
            'data' => json_encode($data)
        ]);

        return $response->json();
    }

    private function checkDeliveryStatusByConsignmentId($id)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/status_by_cid/' . $id);

        return $response->json();
    }


    private function checkDeliveryStatusByInvoiceId($id)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/status_by_invoice/' . $id);

        return $response->json();
    }

    private function checkDeliveryStatusByTrackingCode($id)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/status_by_trackingcode/' . $id);

        return $response->json();
    }
}
