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
            [
                'slug' => 'pathao',
                'title' => 'Pathao',
                'logo' => asset('images/pathao.png')
            ],
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
        $rules = [
            'id' => 'nullable|integer|exists:courier_configurations,id',
            'title' => ['required', 'string'],
            'slug' => ['required', 'string', Rule::in($this->vendors)],
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
        ];

        if ($request->slug === 'pathao') {
            $rules['settings.username'] = 'required|string';
            $rules['settings.password'] = 'nullable|string';
            $rules['settings.sender_name'] = 'nullable|string';
            $rules['settings.sender_phone'] = 'nullable|string';
            $rules['settings.store_id'] = 'nullable';
            $rules['settings.recipient_city'] = 'nullable|integer|min:0';
            $rules['settings.recipient_zone'] = 'nullable|integer|min:0';
            $rules['settings.recipient_area'] = 'nullable|integer|min:0';

            if ($request->boolean('is_active')) {
                $rules['settings.store_id'] = 'required';
                $rules['settings.sender_name'] = 'required|string';
                $rules['settings.sender_phone'] = 'required|string';
                $rules['settings.recipient_city'] = 'required|integer|min:1';
                $rules['settings.recipient_zone'] = 'required|integer|min:1';
                $rules['settings.recipient_area'] = 'required|integer|min:1';
            }
        }

        $validator = Validator::make($request->all(), $rules);

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

        if ($request->slug === 'pathao' && is_array($request->settings)) {
            $existingSettings = [];

            if ($request->filled('id')) {
                $existing = CourierConfiguration::find($request->id);
                $existingSettings = is_array($existing?->settings) ? $existing->settings : [];
            }

            $password = $request->input('settings.password') ?: ($existingSettings['password'] ?? '');

            if (empty($password)) {
                return $this->validationErrorResponse([
                    'settings.password' => ['Pathao login password is required.'],
                ]);
            }

            $data['settings'] = array_merge($existingSettings, [
                'environment' => $request->input('settings.environment') === 'live' ? 'live' : 'sandbox',
                'store_id' => $this->pathaoStringSetting(
                    $request->input('settings.store_id'),
                    $existingSettings['store_id'] ?? ''
                ),
                'username' => $request->input('settings.username'),
                'password' => $password,
                'sender_name' => $this->pathaoStringSetting(
                    $request->input('settings.sender_name'),
                    $existingSettings['sender_name'] ?? ''
                ),
                'sender_phone' => $this->pathaoStringSetting(
                    $request->input('settings.sender_phone'),
                    $existingSettings['sender_phone'] ?? ''
                ),
                'recipient_city' => $this->pathaoIntSetting(
                    $request->input('settings.recipient_city'),
                    $existingSettings['recipient_city'] ?? null
                ),
                'recipient_zone' => $this->pathaoIntSetting(
                    $request->input('settings.recipient_zone'),
                    $existingSettings['recipient_zone'] ?? null
                ),
                'recipient_area' => $this->pathaoIntSetting(
                    $request->input('settings.recipient_area'),
                    $existingSettings['recipient_area'] ?? null
                ),
                'delivery_type' => (int) ($request->input('settings.delivery_type') ?: ($existingSettings['delivery_type'] ?? 48)),
                'item_type' => (int) ($request->input('settings.item_type') ?: ($existingSettings['item_type'] ?? 2)),
                'item_weight' => (float) ($request->input('settings.item_weight') ?: ($existingSettings['item_weight'] ?? 0.5)),
                'item_quantity' => (int) ($request->input('settings.item_quantity') ?: ($existingSettings['item_quantity'] ?? 1)),
            ]);
        }

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

        $query->whereIn('slug', ['steadfast', 'pathao']);

        $query->where(['user_id' => Auth::id()]);

        $config = collect($query->get() ?? []);

        $data = [
            'steadfast' => new \stdClass(),
            'pathao' => new \stdClass(),
        ];

        foreach ($config as $item) {
            if ($item->logo) {
                $item->logo = asset('images/'.$item->slug.'.png');
            }

            if ($item->slug === 'pathao' && is_array($item->settings)) {
                $settings = $item->settings;
                unset($settings['access_token'], $settings['refresh_token'], $settings['expires_at']);
                $settings['password'] = '';
                $item->settings = $settings;
            }

            $data[$item->slug] = $item;
        }
        return $this->successResponse($data);
    }

    private function pathaoStringSetting($value, $fallback = '')
    {
        if ($value === null) {
            return (string) $fallback;
        }

        $next = trim((string) $value);

        return $next !== '' ? $next : (string) $fallback;
    }

    private function pathaoIntSetting($value, $fallback = null)
    {
        if ($value === null || $value === '') {
            return $fallback !== null && $fallback !== '' ? (int) $fallback : null;
        }

        return (int) $value;
    }
}
