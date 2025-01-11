<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use Enan\PathaoCourier\Facades\PathaoCourier;
use Enan\PathaoCourier\Requests\PathaoOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PathaoController extends Controller
{
    protected $baseUrl;
    protected $apiKey;
    protected $secretKey;
    protected $client_id;
    protected $client_secret;
    protected $username;
    protected $password;
    protected $grant_type;

    public function __construct()
    {
        $this->baseUrl = 'https://courier-api-sandbox.pathao.com';
        $this->client_id = '7N1aMJQbWm';
        $this->client_secret = 'wRcaibZkUdSNz2EI9ZyuXLlNrnAv0TdPUPXMnD39';
        $this->username = 'test@pathao.com';
        $this->password = 'lovePathao';
        $this->grant_type = 'password';
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

    /**
     * Usage: PathaoCourier::CREATE_ORDER($request)
     * 
     * This will create a order in Pathao courier merchant
     * @param \Enan\PathaoCourier\Requests\PathaoOrderRequest $request
     * 
     * Request parameters are below and will follow a validation
     * @param $store_id <required, numeric>
     * @param $merchant_order_id <nullable, string>
     * @param $sender_name <required, numeric>
     * @param $sender_phone <required, string/>
     * @param $recipient_name <required, string>
     * @param $recipient_phone <required, string>
     * @param $recipient_address <required, string, Min:10>
     * @param $recipient_city <required, numeric>
     * @param $recipient_zone <required, numeric>
     * @param $recipient_area <required, numeric>
     * @param $delivery_type <required, numeric> is provided by the merchant and not changeable. 48 for Normal Delivery, 12 for On Demand Delivery"
     * @param $item_type <required, numeric> is provided by the merchant and not changeable. 1 for Document, 2 for Parcel"
     * @param $special_instruction <nullable, string>
     * @param $item_quantity <required, numeric>
     * @param $item_weight <required, numeric>
     * @param $amount_to_collect <required, numeric>
     * @param $item_description <nullable, string>
     * 
     * @return array
     */
    const DELIVERY_TYPE_NORMAL = 48;
    const DELIVERY_TYPE_DEMAND = 12;
    const ITEM_TYPE_DOCUMENT = 1;
    const ITEM_TYPE_PARCEL = 2;

    private function getConfiguration() {
        $config = CourierConfiguration::where('user_id', Auth::id())
            ->where('slug', 'pathao')
            ->first();
        if(!$config || !$config->api_key || !$config->secret_key || !@$config->settings->store_id) {
            return false;
        }

        return $config;
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        $config = $this->getConfiguration();

        if(!$config) {
            return $this->errorResponse('The Pathao settings are not configured properly.');
        }

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $data = [
            'store_id' => @$config->settings->store_id,
            'merchant_order_id' => $request->merchant_order_id,
            'sender_name' => $request->sender_name,
            'sender_phone' => $request->sender_phone,
            'recipient_name' => $request->recipient_name,
            'recipient_phone' => $request->recipient_phone,
            'recipient_address' => $request->recipient_address,
            'recipient_city' => $request->recipient_city,
            'recipient_zone' => $request->recipient_zone,
            'recipient_area' => $request->recipient_area,
            'delivery_type' => $request->delivery_type,
            'item_type' => $request->item_type,
            'special_instruction' => $request->special_instruction,
            'item_quantity' => $request->item_quantity,
            'item_weight' => $request->item_weight,
            'amount_to_collect' => $request->amount_to_collect,
            'item_description' => $request->item_description
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $config->secret_key,
        ])->post($this->baseUrl . '/aladdin/api/v1/orders', $data);

        return $data;

        // PathaoCourier
        // $response = $this->placeOrder($data);
        // return $this->successResponse($response->consignment, $response->message);
    }

    public function createBulkOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|min:1',
            'orders.*.store_id' => 'required|integer',
            'orders.*.merchant_order_id' => 'nullable|string',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|string',
            'orders.*.recipient_address' => 'required|string|min:10',
            'orders.*.recipient_city' => 'required|integer',
            'orders.*.recipient_zone' => 'required|integer',
            'orders.*.recipient_area' => 'nullable|integer',
            'orders.*.delivery_type' => 'required|integer|in:48,12',
            'orders.*.item_type' => 'required|integer|in:1,2',
            'orders.*.special_instruction' => 'nullable|string',
            'orders.*.item_quantity' => 'required|integer|min:1',
            'orders.*.item_weight' => 'required|numeric|min:0.5|max:10',
            'orders.*.item_description' => 'nullable|string',
            'orders.*.amount_to_collect' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $orders = $request->input('orders');

        $payload = [
            'orders' => $orders,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json; charset=UTF-8',
            'Authorization' => 'Bearer ' . env('API_ACCESS_TOKEN'),
        ])->post($this->baseUrl . '/orders/bulk', $payload);

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

    public function rules()
    {
        return [
            'store_id' => [
                'required',
                'numeric'
            ],
            'merchant_order_id' => [
                'nullable',
                'string'
            ],
            'sender_name' => [
                'required',
                'string'
            ],
            'sender_phone' => [
                'required',
                'string',
                'regex:/^(?:\+880|880|01[3-9])\d{8}$/'
            ],
            'recipient_name' => [
                'required',
                'string'
            ],
            'recipient_phone' => [
                'required',
                'string',
                'regex:/^(?:\+880|880|01[3-9])\d{8}$/'
            ],
            'recipient_address' => [
                'required',
                'string',
                'Min:10'
            ],
            'recipient_city' => [
                'required',
                'numeric'
            ],
            'recipient_zone' => [
                'required',
                'numeric'
            ],
            'recipient_area' => [
                'required',
                'numeric'
            ],
            'delivery_type' => [
                'required',
                'in:' . self::DELIVERY_TYPE_NORMAL . ',' . self::DELIVERY_TYPE_DEMAND
            ],
            'item_type' => [
                'required',
                'in:' . self::ITEM_TYPE_DOCUMENT . ',' . self::ITEM_TYPE_PARCEL
            ],
            'special_instruction' => [
                'nullable',
                'string'
            ],
            'item_quantity' => [
                'required',
                'numeric'
            ],
            'item_weight' => [
                'required',
                'numeric'
            ],
            'amount_to_collect' => [
                'required',
                'numeric'
            ],
            'item_description' => [
                'nullable',
                'string'
            ]
        ];
    }
}
