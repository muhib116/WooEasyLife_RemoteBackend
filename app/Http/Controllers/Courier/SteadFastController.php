<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SteadFastController extends Controller
{
    protected $baseUrl;
    protected $apiKey;
    protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://portal.steadfast.com.bd/api/v1';
        $this->apiKey = 'j2a4jnjre3fv87rg41yyolpmlzu7os80';
        $this->secretKey = 'rmxck4fxysvp8u3nwjcfgm3t';
    }

    public function checkBalance()
    {
        $steadfastData = CourierConfiguration::where('user_id', Auth::id())
            ->where('slug', 'steadfast')
            ->first();

        if ($steadfastData && $steadfastData->api_key && $steadfastData->secret_key) {
            $response = Http::withHeaders([
                'Api-Key' => $steadfastData->api_key,
                'Secret-Key' => $steadfastData->secret_key,
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
        } else {
            return $this->errorResponse('Configuration issue');
        }
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipient_name' => 'required',
            'recipient_phone' => 'required',
            'recipient_address' => 'required',
            'cod_amount' => 'required',
        ]);
        if (!$validator->valid()) {
            return $this->validationErrorResponse($validator->errors());
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
        return $this->successResponse($response->consignment, $response->message);
    }

    public function createBulkOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|string',
            'orders.*.recipient_address' => 'required|string',
            'orders.*.cod_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
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

        $response = $this->bulkCreateOrders($data);

        return $this->successResponse($response);
    }

    private function placeOrder($data)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/create_order', $data);

        return $response->json();
    }

    private function bulkCreateOrders($data)
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
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
