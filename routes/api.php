<?php

use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\Message\PutMessageController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::any('/fraud-check', [FraudCheckController::class, 'check'])->name('fraudCheck');

Route::get('/send-message', [FollowUpController::class, 'sendMessage']);

Route::post('/put-message', [PutMessageController::class, 'putMessage']);
