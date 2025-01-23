<?php

use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Courier\ConfigurationController;
use App\Http\Controllers\Courier\PathaoController;
use App\Http\Controllers\Courier\RedXController;
use App\Http\Controllers\Courier\SteadFastController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\PackageHub\PackageController;
use App\Http\Controllers\SmsController;
use Illuminate\Http\Request;
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

Route::get('app-logo', [PluginsController::class, 'appLogo']);

Route::get('download-plugins', [PluginsController::class, 'downloadApp']);
Route::get('get-metadata', [PluginsController::class, 'getMetadata']);

Route::group(['middleware' => 'check.token', 'prefix' => 'api'], function () {

    Route::get('/get-user', function () {
        return redirect()->route('getUserData');
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/get-user-data', function (Request $request) {
            return $request->user();
        })->name('getUserData');

        Route::get('/validate-token', function (Request $request) {
            return true;
        })->name('apiValidate');

        Route::group(['as' => 'courier.', 'prefix' => 'courier'], function () {
            Route::post('/list', [ConfigurationController::class, 'getList']);
            Route::post('/save-configuration', [ConfigurationController::class, 'saveConfiguration']);
            Route::post('/get-configuration', [ConfigurationController::class, 'getConfiguration']);
        });

        Route::get('check-courier-balance', [SteadFastController::class, 'checkCourierBalance']);

        Route::group(['as' => 'steadfast.', 'prefix' => 'steadfast'], function () {
            Route::post('/create-order', [SteadFastController::class, 'createOrder']);
            Route::post('/create-bulk-order', [SteadFastController::class, 'createBulkOrder']);
            Route::post('/check-status', [SteadFastController::class, 'checkStatus']);
            Route::post('/bulk-check-status', [SteadFastController::class, 'bulkCheckStatus']);
            Route::post('/check-balance', [SteadFastController::class, 'checkBalance']);
        });

        Route::group(['as' => 'pathao.', 'prefix' => 'pathao'], function () {
            Route::post('/create-order', [PathaoController::class, 'createOrder']);
            Route::post('/create-bulk-order', [PathaoController::class, 'createBulkOrder']);
            Route::post('/check-balance', [PathaoController::class, 'checkBalance']);
        });

        Route::group(['as' => 'redx.', 'prefix' => 'redx'], function () {
            Route::post('/get-areas', [RedXController::class, 'getArea']);
            Route::post('/create-order', [RedXController::class, 'createOrder']);
            Route::post('/track-parcel', [RedXController::class, 'trackParcel']);
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

        Route::post('/fraud-check', [FraudCheckController::class, 'check'])->name('fraudCheck');
    });
});
