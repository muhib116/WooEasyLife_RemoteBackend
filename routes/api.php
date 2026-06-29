<?php

use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Courier\CourierWebhookOpsController;
use App\Http\Controllers\Courier\WebhookHubController;
use App\Http\Controllers\Courier\ConfigurationController;
use App\Http\Controllers\Courier\PathaoController;
use App\Http\Controllers\Courier\RedXController;
use App\Http\Controllers\Courier\SteadFastController;
use App\Http\Controllers\Data\DataController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\Hub\HubController;
use App\Http\Controllers\Plugin\EmployeeController as PluginEmployeeController;
use App\Http\Controllers\SmsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Route::middleware('auth:sanctum')->get('/validate-token', function (Request $request) {
//     return true;
// });
Route::group(['middleware' => ['check.tokenDomain'], 'prefix' => 'api'], function () {
    Route::get('/get-user', [UserController::class, 'getUser']);
});

Route::group(['middleware' => ['auth.packageRenewal'], 'prefix' => 'api/package', 'as' => 'package.'], function () {
    Route::get('/plans', [\App\Http\Controllers\PackagePaymentController::class, 'plans']);
    Route::post('/payment-request', [\App\Http\Controllers\PackagePaymentController::class, 'createRequest']);
    Route::get('/payment-history', [\App\Http\Controllers\PackagePaymentController::class, 'history']);
});

Route::get('app-logo', [PluginsController::class, 'appLogo']);
Route::get('brand-asset/{asset}', [PluginsController::class, 'brandAsset'])
    ->where('asset', 'icon-128\.png|icon-256\.png|app_logo\.png|app_icon\.jpg');
Route::get('download-plugins', [PluginsController::class, 'downloadApp']);
Route::get('get-metadata', [PluginsController::class, 'getMetadata']);

Route::prefix('api/webhooks')->group(function () {
    Route::post('/pathao', [WebhookHubController::class, 'pathao']);
    Route::post('/pathao/sandbox', function (Request $request) {
        return app(WebhookHubController::class)->pathao($request, 'sandbox');
    });
    Route::post('/steadfast', [WebhookHubController::class, 'steadfast']);
    Route::post('/steadfast/sandbox', function (Request $request) {
        return app(WebhookHubController::class)->steadfast($request, 'sandbox');
    });
    Route::post('/redx', [WebhookHubController::class, 'redx']);
    Route::post('/redx/sandbox', function (Request $request) {
        return app(WebhookHubController::class)->redx($request, 'sandbox');
    });
});

$pathaoDevCatalog = app()->environment('local')
    || filter_var(env('PATHAO_DEV_CATALOG', false), FILTER_VALIDATE_BOOLEAN);

$registerPathaoCatalogRoutes = function () {
    Route::post('/stores', [PathaoController::class, 'getStores']);
    Route::post('/city-list', [PathaoController::class, 'getCities']);
    Route::post('/cities', [PathaoController::class, 'getCities']);
    Route::post('/cities/{cityId}/zone-list', [PathaoController::class, 'getZonesByCity'])
        ->where('cityId', '[0-9]+');
    Route::post('/zones', [PathaoController::class, 'getZones']);
    Route::post('/zones/{zoneId}/area-list', [PathaoController::class, 'getAreasByZone'])
        ->where('zoneId', '[0-9]+');
    Route::post('/areas', [PathaoController::class, 'getAreas']);
    Route::post('/create-store', [PathaoController::class, 'createStore']);
    Route::post('/update-store', [PathaoController::class, 'updateStore']);
    Route::post('/delete-store', [PathaoController::class, 'deleteStore']);
    Route::post('/price-plan', [PathaoController::class, 'pricePlan']);
    Route::post('/test-connection', [PathaoController::class, 'testConnection']);
    Route::post('/reset-token', [PathaoController::class, 'resetToken']);
    Route::post('/merchant-info', [PathaoController::class, 'merchantInfo']);
};

/*
| Dev Pathao catalog proxy using credentials sent in the request body.
| Enable with APP_ENV=local OR PATHAO_DEV_CATALOG=true in .env
*/
if ($pathaoDevCatalog) {
    Route::prefix('api/pathao')->group($registerPathaoCatalogRoutes);
}

Route::group(['middleware' => ['check.token', 'check.tokenDomain'], 'prefix' => 'api'], function () use ($pathaoDevCatalog, $registerPathaoCatalogRoutes) {

    Route::middleware(['auth:sanctum'])->group(function () use ($pathaoDevCatalog, $registerPathaoCatalogRoutes) {
        Route::get('/get-user-data', function (Request $request) {
            return $request->user();
        })->name('getUserData');

        Route::get('/validate-token', function (Request $request) {
            return true;
        })->name('apiValidate');

        Route::any('get-tutorials', [DataController::class, 'getTutorials']);
        Route::any('get-contact-info', [DataController::class, 'getContactInfo']);

        // use of package order limit
        Route::post('package-order-use', [HubController::class, 'hubUse']);

        Route::get('employees', [PluginEmployeeController::class, 'index']);
        Route::get('employees/{employee_id}', [PluginEmployeeController::class, 'show'])
            ->whereNumber('employee_id');
        Route::post('employees', [PluginEmployeeController::class, 'store']);
        Route::put('employees/{employee_id}', [PluginEmployeeController::class, 'update'])
            ->whereNumber('employee_id');
        Route::patch('employees/{employee_id}', [PluginEmployeeController::class, 'update'])
            ->whereNumber('employee_id');
        Route::delete('employees/{employee_id}', [PluginEmployeeController::class, 'destroy'])
            ->whereNumber('employee_id');

        Route::group(['as' => 'courier.', 'prefix' => 'courier'], function () {
            Route::post('/list', [ConfigurationController::class, 'getList']);
            Route::post('/save-configuration', [ConfigurationController::class, 'saveConfiguration']);
            Route::post('/get-configuration', [ConfigurationController::class, 'getConfiguration']);
            Route::get('/webhook-settings', [ConfigurationController::class, 'getWebhookSettings']);
            Route::post('/webhook-sync', [CourierWebhookOpsController::class, 'webhookSync']);
            Route::get('/webhook-health', [CourierWebhookOpsController::class, 'webhookHealth']);
            Route::get('/webhook-events', [CourierWebhookOpsController::class, 'webhookEvents']);
            Route::post('/backfill-shipments', [CourierWebhookOpsController::class, 'backfillShipments']);
        });

        Route::get('check-courier-balance', [SteadFastController::class, 'checkCourierBalance']);

        Route::group(['as' => 'steadfast.', 'prefix' => 'steadfast'], function () {
            Route::post('/create-order', [SteadFastController::class, 'createOrder']);
            Route::post('/create-bulk-order', [SteadFastController::class, 'createBulkOrder']);
            Route::post('/check-status', [SteadFastController::class, 'checkStatus']);
            Route::post('/bulk-check-status', [SteadFastController::class, 'bulkCheckStatus']);
            Route::post('/check-balance', [SteadFastController::class, 'checkBalance']);
        });

        Route::group(['as' => 'pathao.', 'prefix' => 'pathao'], function () use ($pathaoDevCatalog, $registerPathaoCatalogRoutes) {
            Route::post('/create-order', [PathaoController::class, 'createOrder']);
            Route::post('/create-bulk-order', [PathaoController::class, 'createBulkOrder']);
            Route::post('/check-status', [PathaoController::class, 'checkStatus']);
            Route::post('/bulk-check-status', [PathaoController::class, 'bulkCheckStatus']);
            Route::post('/check-balance', [PathaoController::class, 'checkBalance']);

            if (!$pathaoDevCatalog) {
                $registerPathaoCatalogRoutes();
            }
        });

        Route::group(['as' => 'redx.', 'prefix' => 'redx'], function () {
            Route::post('/test-connection', [RedXController::class, 'testConnection']);
            Route::post('/get-areas', [RedXController::class, 'getArea']);
            Route::post('/pickup-stores', [RedXController::class, 'getPickupStores']);
            Route::post('/charge-calculator', [RedXController::class, 'chargeCalculator']);
            Route::post('/create-order', [RedXController::class, 'createOrder']);
            Route::post('/track-parcel', [RedXController::class, 'trackParcel']);
            Route::post('/bulk-track-status', [RedXController::class, 'bulkTrackStatus']);
            Route::post('/create-bulk-order', [RedXController::class, 'createBulkOrder']);
            Route::post('/check-balance', [RedXController::class, 'checkBalance']);
        });

        Route::group(['as' => 'sms.', 'prefix' => 'sms'], function () {
            Route::post('/send', [SmsController::class, 'send']);
            Route::post('/recharge', [SmsController::class, 'recharge']);
            Route::get('/recharge-history', [SmsController::class, 'rechargeHistory']);
            Route::get('/use-history', [SmsController::class, 'useHistory']);
            Route::get('/balance', [SmsController::class, 'smsBalance']);
        });

        Route::post('/fraud-check', [FraudCheckController::class, 'check'])
            ->middleware('check.fraudWhitelist')
            ->name('fraudCheck');
        Route::post('/fraud-check-stream', [FraudCheckController::class, 'checkStream'])
            ->middleware('check.fraudWhitelist')
            ->name('checkStream');
    });
});
