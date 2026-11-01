<?php

use App\Http\Controllers\App\CourierCheckLegalController;
use App\Http\Controllers\CourierCheckApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->prefix('api/courier-check/v1')
    ->name('courier-check.')
    ->group(function () {
        Route::post('/check', [CourierCheckApiController::class, 'check'])
            ->middleware('throttle:30,1')
            ->name('check');
        Route::post('/register', [CourierCheckApiController::class, 'register'])
            ->middleware('throttle:30,1')
            ->name('register');
    });

Route::middleware('web')
    ->prefix('wooeasylife/courier-check')
    ->name('wooeasylife.courier-check.')
    ->group(function () {
        Route::get('/privacy-policy', [CourierCheckLegalController::class, 'privacyPolicy'])
            ->name('privacy-policy');
        Route::get('/terms-of-service', [CourierCheckLegalController::class, 'termsOfService'])
            ->name('terms-of-service');
    });
