<?php

use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Courier\ConfigurationController;
use App\Http\Controllers\Courier\PathaoController;
use App\Http\Controllers\Courier\RedXController;
use App\Http\Controllers\Courier\SteadFastController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\Message\PutMessageController;
use App\Http\Controllers\SmsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
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

Route::middleware('auth:sanctum')->get('/get-user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->get('/validate-token', function (Request $request) {
    return true;
});

Route::get('app-logo', function() {
    $path = public_path('logo.webp');
    
    if (!file_exists($path)) {
        abort(404);
    }

    $file = file_get_contents($path);
    $type = mime_content_type($path);

    return Response::make($file, 200, [
        'Content-Type' => $type,
        'Content-Disposition' => 'inline',
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('download-plugins', [PluginsController::class, 'downloadApp']);

    Route::group(['as' => 'courier.', 'prefix' => 'courier'], function () {
        Route::post('/list', [ConfigurationController::class, 'getList']);
        Route::post('/save-configuration', [ConfigurationController::class, 'saveConfiguration']);
        Route::post('/get-configuration', [ConfigurationController::class, 'getConfiguration']);
    });

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

