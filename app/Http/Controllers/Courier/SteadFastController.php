<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SteadFastController extends Controller
{
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

        $url = 'https://portal.packzy.com/api/v1';

        $data = [
            'invoice' => '',
            'recipient_name' => '',
            'recipient_phone' => '',
            'recipient_address' => '',
            'cod_amount' => '',
            'note' => '',
        ];

        $headers = [
            'Api-Key' => 'YOUR_API_KEY',
            'Secret-Key' => 'YOUR_SECRET_KEY',
            'Content-Type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->post($url, $data);

        if ($response->successful()) {
            return $response->json(); // Return the successful response
        } else {
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $response->body(),
            ], $response->status());
        }
    }

    public function bulkCreate2($data)
    {

        $api_key = '1m9mwrrwsjbrg0w';

        $secret_key = 'y196ftazvk9s3';


        $response = Http::withHeaders([

            'Api-Key' => $api_key,

            'Secret-Key' => $secret_key,

            'Content-Type' => 'application/json'

        ])->post('https://portal.packzy.com/api/v1' . '/create_order/bulk-order', [

            'data' => $data,

        ]);

        return json_decode($response->getBody()->getContents());
    }
}
