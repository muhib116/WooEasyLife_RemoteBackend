<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use App\Services\Courier\CourierAccountService;
use App\Services\Courier\CourierWebhookSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public function __construct(
        protected CourierAccountService $courierAccountService,
        protected CourierWebhookSettingsService $webhookSettingsService
    ) {
    }

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
            [
                'slug' => 'redx',
                'title' => 'RedX',
                'logo' => asset('images/redx.svg')
            ],
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

        if ($request->slug === 'redx') {
            $rules['secret_key'] = $request->filled('id') ? 'nullable|string' : 'required|string';
            $rules['settings.environment'] = 'nullable|string|in:live,sandbox';
            $rules['settings.pickup_store_id'] = 'nullable|integer|min:1';
            $rules['settings.delivery_area_id'] = 'nullable|integer|min:1';
            $rules['settings.delivery_area'] = 'nullable|string|max:255';
            $rules['settings.parcel_weight'] = 'nullable|numeric|min:500';

            if ($request->boolean('is_active')) {
                $rules['settings.delivery_area_id'] = 'required|integer|min:1';
            }
        }

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

        if ($request->slug === 'redx') {
            $existingSettings = [];

            if ($request->filled('id')) {
                $existing = CourierConfiguration::find($request->id);
                $existingSettings = is_array($existing?->settings) ? $existing->settings : [];
            }

            $secretKey = trim((string) $request->secret_key);
            if ($secretKey === '' && $request->filled('id')) {
                $existing = CourierConfiguration::find($request->id);
                $secretKey = trim((string) ($existing?->secret_key ?? ''));
            }

            if ($secretKey === '') {
                return $this->validationErrorResponse([
                    'secret_key' => ['RedX API access token is required.'],
                ]);
            }

            $data['api_key'] = trim((string) ($request->api_key ?: 'redx')) ?: 'redx';
            $data['secret_key'] = $secretKey;
            $data['settings'] = array_merge($existingSettings, [
                'environment' => $request->input('settings.environment') === 'live' ? 'live' : 'sandbox',
                'pickup_store_id' => $this->pathaoIntSetting(
                    $request->input('settings.pickup_store_id'),
                    $existingSettings['pickup_store_id'] ?? null
                ),
                'delivery_area_id' => $this->pathaoIntSetting(
                    $request->input('settings.delivery_area_id'),
                    $existingSettings['delivery_area_id'] ?? null
                ),
                'delivery_area' => $this->pathaoStringSetting(
                    $request->input('settings.delivery_area'),
                    $existingSettings['delivery_area'] ?? ''
                ),
                'parcel_weight' => max(
                    500,
                    (int) ($request->input('settings.parcel_weight') ?: ($existingSettings['parcel_weight'] ?? 500))
                ),
            ]);
        }

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
                'webhook_secret' => $this->pathaoStringSetting(
                    $request->input('settings.webhook_secret'),
                    $existingSettings['webhook_secret'] ?? ''
                ),
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

        $webhookSecretOverride = null;
        if ($request->slug === 'pathao' && is_array($request->settings)) {
            $webhookSecretOverride = trim((string) ($request->input('settings.webhook_secret') ?? ''));
        }

        $sync = $this->courierAccountService->syncAccountForConfiguration(
            $configuration,
            $request,
            $webhookSecretOverride !== '' ? $webhookSecretOverride : null
        );

        $accessToken = $this->courierAccountService->resolveAccessToken($request);
        $inFlight = 0;

        if ($accessToken && !empty($sync['credentials_changed'])) {
            $inFlight = $this->courierAccountService->countInFlightShipments(
                $accessToken,
                (string) $configuration->slug,
                (int) ($sync['courier_account_id'] ?? 0)
            );
        }

        $webhookSettings = $this->webhookSettingsService->buildSettings((string) $configuration->slug, $request, [
            'credentials_changed' => (bool) ($sync['credentials_changed'] ?? false),
            'in_flight_shipments' => $inFlight,
        ]);

        $responseData = $configuration->toArray();
        $responseData['webhook_settings'] = $webhookSettings;
        $responseData['credentials_changed'] = (bool) ($sync['credentials_changed'] ?? false);

        return $this->successResponse($responseData, 'Configuration saved successfully!');
    }

    public function getWebhookSettings(Request $request)
    {
        $partner = strtolower(trim((string) $request->query('partner', '')));

        if (!in_array($partner, ['steadfast', 'pathao', 'redx'], true)) {
            return $this->errorResponse('Invalid courier partner.');
        }

        return $this->successResponse(
            $this->webhookSettingsService->buildSettings($partner, $request)
        );
    }


    public function getConfiguration(Request $request)
    {
        $query = CourierConfiguration::query();

        $query->whereIn('slug', ['steadfast', 'pathao', 'redx']);

        $query->where(['user_id' => Auth::id()]);

        $config = collect($query->get() ?? []);

        $data = [
            'steadfast' => new \stdClass(),
            'pathao' => new \stdClass(),
            'redx' => new \stdClass(),
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

            if ($item->slug === 'redx') {
                $item->secret_key = '';
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
