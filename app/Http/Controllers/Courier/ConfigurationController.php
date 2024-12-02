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
                'title' => 'Pathao',
                'key' => 'pathao'
            ],
            [
                'title' => 'PaperFly',
                'key' => 'paperfly'
            ],
            [
                'title' => 'Steadfast',
                'key' => 'steadfast'
            ],
            [
                'title' => 'RedX',
                'key' => 'redx'
            ],
        ];
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
        // $validator = Validator::make($request->all(), [
        //     'title' => ['required', 'string', Rule::in($this->vendors)],
        // ]);
        // if (!$validator->valid()) {
        //     return $this->validationErrorResponse($validator->errors());
        // }

        // $config = CourierConfiguration::where(['title' => $request->title])->find(['user_id' => Auth::id()]);

        $query = CourierConfiguration::query();
        if ($request->title) {
            $query->where(['title' => $request->title]);
        }

        $query->where(['user_id' => Auth::id()]);

        $config = $query->get();

        return $this->successResponse($config);
    }
}
