<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{

    public $vendors = ['pathao', 'paperfly', 'steadfast', 'redx'];

    public function getList()
    {
        $formatted = [
            [
                'slug' => 'steadfast',
                'title' => 'Steadfast',
            ],
            [
                'slug' => 'pathao',
                'title' => 'Pathao',
            ],
            [
                'slug' => 'paperfly',
                'title' => 'Paperfly',
            ],
            [
                'slug' => 'redx',
                'title' => 'RedX',
            ],
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
            'user_id' => Auth::id(),
        ];
    
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
        if ($request->title) {
            $query->where(['title' => $request->title]);
        }

        $query->where(['user_id' => Auth::id()]);

        $config = collect($query->get() ?? []);

        $data = [
            'pathao' => new \stdClass(),
            'paperfly' => new \stdClass(),
            'steadfast' => new \stdClass(),
            'redx' => new \stdClass(),
        ];

        foreach ($config as $item) {
            $data[$item->slug] = $item;
        }
        return $this->successResponse($data);
    }
}