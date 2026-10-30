<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierConfiguration;
use App\Services\Courier\CourierLicenseSyncService;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\PaperflyCourierService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaperflySettingsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected CourierLicenseSyncService $licenseSync,
        protected PaperflyCourierService $paperfly,
        protected CourierShipmentService $shipments,
    ) {
    }

    public function testConnection(Request $request)
    {
        $config = $this->resolveConfig($request);

        if (! $config) {
            return $this->errorResponse('Paperfly configuration not found. Save username and password first.', 404);
        }

        $result = $this->paperfly->testConnection($config);

        if (! ($result['ok'] ?? false)) {
            return $this->errorResponse($result['message'] ?? 'Paperfly connection failed.', 400);
        }

        return $this->successResponse($result, $result['message'] ?? 'Paperfly login verified.');
    }

    public function stores()
    {
        $config = $this->savedConfig();
        if ($config instanceof JsonResponse) {
            return $config;
        }

        return $this->storeResult($this->paperfly->listStores($config));
    }

    public function createStore(Request $request)
    {
        $config = $this->savedConfig();
        if ($config instanceof JsonResponse) {
            return $config;
        }

        return $this->storeResult($this->paperfly->createStore($config, $request->all()));
    }

    public function updateStore(Request $request)
    {
        $config = $this->savedConfig();
        if ($config instanceof JsonResponse) {
            return $config;
        }

        return $this->storeResult($this->paperfly->updateStore($config, $request->all()));
    }

    public function setDefaultStore(Request $request)
    {
        $config = $this->savedConfig();
        if ($config instanceof JsonResponse) {
            return $config;
        }

        return $this->storeResult($this->paperfly->setDefaultStore($config, (string) $request->input('id', '')));
    }

    public function createBulkOrder(Request $request)
    {
        $config = $this->savedConfig();
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $settings = is_array($config->settings) ? $config->settings : [];
        $store = is_array($settings['active_store'] ?? null) ? $settings['active_store'] : [];
        if (trim((string) ($store['id'] ?? '')) === '' || trim((string) ($store['full_name'] ?? '')) === '') {
            return $this->errorResponse('A default Paperfly store is required.');
        }

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|max:50',
            'orders.*.invoice' => 'required|string',
            'orders.*.recipient_name' => 'required|string',
            'orders.*.recipient_phone' => 'required|string',
            'orders.*.recipient_address' => 'required|string',
            'orders.*.cod_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->messages());
        }

        $rows = $this->paperfly->createOrders($config, $request->input('orders', []));
        $rows = $this->shipments->recordSuccessfulOrders('paperfly', $config, $rows, $request);

        return $this->successResponse($rows);
    }

    protected function resolveConfig(Request $request): ?CourierConfiguration
    {
        $config = $this->licenseSync->bookingConfiguration((int) auth()->id(), 'paperfly');

        if (! $config) {
            $config = CourierConfiguration::query()
                ->where('user_id', auth()->id())
                ->where('slug', 'paperfly')
                ->latest('id')
                ->first();
        }

        if (! $config) {
            return null;
        }

        $username = trim((string) $request->input('username', $request->input('api_key', '')));
        $password = trim((string) $request->input('password', $request->input('secret_key', '')));

        if ($username === '' && $password === '') {
            return $config;
        }

        $probe = $config->replicate();
        $probe->id = $config->id;
        $settings = is_array($probe->settings) ? $probe->settings : [];

        if ($username !== '') {
            $probe->api_key = $username;
            $settings['username'] = $username;
        }

        if ($password !== '') {
            $probe->secret_key = $password;
        }

        $probe->settings = $settings;

        return $probe;
    }

    protected function savedConfig(): CourierConfiguration|JsonResponse
    {
        $config = $this->licenseSync->bookingConfiguration((int) auth()->id(), 'paperfly');

        if (! $config) {
            $config = CourierConfiguration::query()
                ->where('user_id', auth()->id())
                ->where('slug', 'paperfly')
                ->latest('id')
                ->first();
        }

        if (! $config || trim((string) $config->api_key) === '' || trim((string) $config->secret_key) === '') {
            return $this->errorResponse('Save the Paperfly password first.', 422);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function storeResult(array $result)
    {
        if (! ($result['ok'] ?? false)) {
            $status = ! empty($result['validation']) ? 422 : 400;

            return $this->errorResponse($result['message'] ?? 'Paperfly request failed.', $status);
        }

        unset($result['ok']);

        return $this->successResponse($result);
    }
}
