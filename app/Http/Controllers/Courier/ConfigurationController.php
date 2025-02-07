<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{

    public $vendors = ['pathao', 'paperfly', 'steadfast', 'redx'];
    public $baseUrl = 'https://portal.packzy.com/api/v1';

    public function getList()
    {
        $formatted = [
            [
                'slug' => 'steadfast',
                'title' => 'Steadfast',
                'logo' => asset('images/steadfast.png')
                // "logo": "http://localhost:8000/images/steadfast.png",
            ],
            // [
            //     'slug' => 'pathao',
            //     'title' => 'Pathao',
            //     'logo' => asset('images/pathao.png')
            // ],
            // [
            //     'slug' => 'paperfly',
            //     'title' => 'Paperfly',
            //     'logo' => asset('images/paperfly.png')
            // ],
            // [
            //     'slug' => 'redx',
            //     'title' => 'RedX',
            //     'logo' => asset('images/redx.png')
            // ],
        ];

        return $this->successResponse($formatted);
    }

    public function saveConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:courier_configurations,id',
            'title' => ['required', 'string'],
            'slug' => ['required', 'string', Rule::in($this->vendors)],
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $data = [
            'title' => $request->title,
            'slug' => trim($request->slug),
            'api_key' => trim($request->api_key),
            'secret_key' => trim($request->secret_key),
            'is_active' => $request->is_active,
            'logo' => 'images/'.trim($request->slug).'.png',
            'user_id' => Auth::id(),
        ];

        // $statusCode = 200;
        // try {
        //     $response = Http::withHeaders([
        //         'Api-Key' => $data['api_key'],
        //         'Secret-Key' => $data['secret_key'],
        //         'Content-Type' => 'application/json',
        //     ])->get($this->baseUrl . '/get_balance');
        //     $statusCode = $response->status();
        // } catch (\Throwable $th) {
        // }

        // if ($statusCode != 200) {
        //     return $this->errorResponse('Invalid api key or secret key.');
        // }

        // Check if ID is provided for an existing record
        if ($request->filled('id')) {
            // Update the existing record
            $configuration = CourierConfiguration::find($request->id);
            $configuration->update($data);
        } else {
            // Create a new record
            $configuration = CourierConfiguration::create($data);
        }

        return $this->successResponse($configuration, 'Configuration saved successfully!');
    }


    public function getConfiguration(Request $request)
    {
        $query = CourierConfiguration::query();

        $query->where('slug', 'steadfast');

        $query->where(['user_id' => Auth::id()]);

        $config = collect($query->get() ?? []);

        $data = [
            'steadfast' => new \stdClass(),
            // 'pathao' => new \stdClass(),
            // 'paperfly' => new \stdClass(),
            // 'redx' => new \stdClass(),
        ];

        foreach ($config as $item) {
            if ($item->logo) {
                $item->logo = asset('images/'.$item->slug.'.png');
            }
            $data[$item->slug] = $item;
        }
        return $this->successResponse($data);
    }
}
