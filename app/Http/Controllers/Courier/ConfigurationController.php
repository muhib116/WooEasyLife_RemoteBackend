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
        $formatted = collect($this->vendors)->map(function ($item) {
            return ucfirst($item);
        });
        return $this->successResponse($formatted);
    }

    public function saveConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', Rule::in($this->vendors)],
            'api_key' => 'required',
            'secret_key' => 'required',
        ]);

        if (!$validator->valid()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $data = [
            'title' => $request->title,
            'api_key' => $request->api_key,
            'secret_key' => $request->secret_key,
            'user_id' => Auth::id()
        ];

        $exist = CourierConfiguration::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return $this->successResponse($exist, 'Configuration Saved Successfully!');
    }

    public function getConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', Rule::in($this->vendors)],
        ]);
        if (!$validator->valid()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $config = CourierConfiguration::where(['title' => $request->name])->find(['user_id' => Auth::id()]);

        return $this->successResponse($config, !$config ? 'No configuration found for ' . $request->name : '');
    }
}
