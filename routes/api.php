<?php

use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Courier\ConfigurationController;
use App\Http\Controllers\Courier\SteadFastController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\Message\PutMessageController;
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

Route::middleware('auth:sanctum')->get('/get-user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::group(['as' => 'courier.', 'prefix' => 'courier'], function () {
        Route::post('/list', [ConfigurationController::class, 'getList']);
        Route::post('/save-configuration', [ConfigurationController::class, 'saveConfiguration']);
        Route::post('/get-configuration', [ConfigurationController::class, 'getConfiguration']);
    });
    Route::group(['as' => 'sms.', 'prefix' => 'sms'], function () {
        Route::post('/send', [SmsController::class, 'send']);
    });
});

Route::post('/fraud-check', [FraudCheckController::class, 'check'])->name('fraudCheck');

Route::group(['as' => 'steadfast.', 'prefix' => 'steadfast'], function () {
    Route::post('/create-order', [SteadFastController::class, 'createOrder']);
    Route::post('/check-balance', [SteadFastController::class, 'checkBalance']);
});
