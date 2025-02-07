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
    protected $baseUrl = 'https://sandbox.redx.com.bd';
    protected $apiKey;
    protected $secretKey;

    protected $token = [
        "API-ACCESS-TOKEN" => "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI5MTY5MDMiLCJpYXQiOjE3MzY3NzA0MjYsImlzcyI6ImhuV1FraTdYZWswb21ObDhJaXg2SmZNMW9pWjNURWxvIiwic2hvcF9pZCI6OTE2OTAzLCJ1c2VyX2lkIjo5NDM0MDA0fQ.2VSeFA5TxsgJPUzL-Fy0Bt3tNnD1V_CY-cJeYPmfkWc",
        "Accept" => "application/json"
    ];

    public function getArea()
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The Redx settings are not configured properly.');
        }
        $link = $this->baseUrl . '/v1.0.0-beta/areas';

        try {
            $response = Http::withHeaders($this->token)->get($link);
            $response = $response->json();
            return $this->successResponse($response);
        } catch (\Throwable $th) {
            return $this->errorResponse('There is some issue to get areas.');
        }
    }

    private function getConfig()
    {
        $config = CourierConfiguration::where('user_id', Auth::id())
            ->where('slug', 'redx')
            ->first();

        if (!$config || !$config->api_key || !$config->secret_key) {
            return false;
        }

        $this->token['API-ACCESS-TOKEN'] = 'Bearer ' . $config->secret_key;
        return $config;
    }

    public function createOrder(Request $request)
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The SteadFast settings are not configured properly.');
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20', // Adjust max length based on phone format
            'delivery_area' => 'required|string|max:255',
            'delivery_area_id' => 'required|integer',
            'customer_address' => 'required|string|max:500',
            'cash_collection_amount' => 'required|numeric|min:0',
            'parcel_weight' => 'required|numeric|min:0',
            'merchant_invoice_id' => 'nullable|string|max:255',
            'instruction' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:reverse', // Optional: Adjust the valid types
            'value' => 'required|numeric|min:0',
            'parcel_details_json' => 'nullable|array', // Ensures it's a valid JSON string
            'pickup_store_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $payload = [
            "customer_name" => $request->customer_name,
            "customer_phone" => $request->customer_phone,
            "delivery_area" => $request->delivery_area,
            "delivery_area_id" => $request->delivery_area_id,
            "customer_address" => $request->customer_address,
            "merchant_invoice_id" => $request->merchant_invoice_id,
            "cash_collection_amount" => $request->cash_collection_amount, // Ensure this is an integer
            "parcel_weight" => $request->parcel_weight, // Weight in grams
            "instruction" => $request->instruction,
            "value" => $request->value, // Compensation value
            "is_closed_box" => $request->is_closed_box,
            "pickup_store_id" => $request->pickup_store_id, // Optional
            "parcel_details_json" => $request->parcel_details_json
        ];

        $link = $this->baseUrl . '/v1.0.0-beta/parcel';

        try {
            $response = Http::withHeaders($this->token)->post($link, $payload);
            $response = $response->json();
            return $this->successResponse($response);
        } catch (\Throwable $th) {
            return $this->errorResponse('An issue occurred while processing the order.');
        }
    }

    public function trackParcel(Request $request)
    {
        $config = $this->getConfig();

        if (!$config) {
            return $this->errorResponse('The Redx settings are not configured properly.');
        }


        $trackId = $request->track_id;
        $link = $this->baseUrl . '/v1.0.0-beta/parcel/track/';
        $parcels = [];

        if (is_array($trackId)) {
            try {
                foreach ($trackId as $id) {
                    $link = $link . $id;
                    $response = Http::withHeaders($this->token)->get($link);
                    $response = $response->json();
                    $parcels[$id] = @$response['tracking'] ?? [];
                }
            } catch (\Throwable $th) {
            }
        } else {
            try {
                $link = $link . $trackId;
                $response = Http::withHeaders($this->token)->get($link);
                $response = $response->json();
                $parcels[$trackId] = @$response['tracking'] ?? [];
                // 20A316MOG0DI
            } catch (\Throwable $th) {
                return $this->errorResponse('An issue occurred while processing the order.');
            }
        }
        return $this->successResponse($parcels);
        // https://sandbox.redx.com.bd/v1.0.0-beta/parcel/info/21A427TU4BN3R
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
