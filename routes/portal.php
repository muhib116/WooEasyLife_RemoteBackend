<?php

use App\Http\Controllers\Portal\BillingController;
use App\Http\Controllers\Portal\BulkFraudCheckController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EmployeeController;
use App\Http\Controllers\Portal\ForcePasswordChangeController;
use App\Http\Controllers\Portal\PasswordSettingsController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.active', 'merchant.portal'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/password/required', [ForcePasswordChangeController::class, 'edit'])
            ->name('password.force');
        Route::put('/password/required', [ForcePasswordChangeController::class, 'update'])
            ->name('password.force.update');

        Route::middleware('password.changed')->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');

            Route::middleware('courier.history')->group(function () {
                Route::get('/fraud-check/search', [BulkFraudCheckController::class, 'searchPage'])
                    ->name('fraud-check.search');
                Route::post('/fraud-check/search', [BulkFraudCheckController::class, 'search'])
                    ->middleware('throttle:30,1')
                    ->name('fraud-check.search.store');
                Route::get('/fraud-check', [BulkFraudCheckController::class, 'index'])
                    ->name('fraud-check');
                Route::get('/fraud-check/batches', [BulkFraudCheckController::class, 'batches'])
                    ->name('fraud-check.batches.index');
                Route::post('/fraud-check/batches', [BulkFraudCheckController::class, 'store'])
                    ->middleware('throttle:20,1')
                    ->name('fraud-check.batches.store');
                Route::get('/fraud-check/batches/{batch}', [BulkFraudCheckController::class, 'show'])
                    ->name('fraud-check.batches.show');
                Route::patch('/fraud-check/batches/{batch}', [BulkFraudCheckController::class, 'update'])
                    ->name('fraud-check.batches.update');
                Route::delete('/fraud-check/batches/{batch}', [BulkFraudCheckController::class, 'destroy'])
                    ->name('fraud-check.batches.destroy');
                Route::post('/fraud-check/batches/{batch}/next', [BulkFraudCheckController::class, 'next'])
                    ->middleware('throttle:120,1')
                    ->name('fraud-check.batches.next');
                Route::post('/fraud-check/batches/{batch}/stop', [BulkFraudCheckController::class, 'stop'])
                    ->name('fraud-check.batches.stop');
                Route::post('/fraud-check/batches/{batch}/resume', [BulkFraudCheckController::class, 'resume'])
                    ->name('fraud-check.batches.resume');
                Route::post('/fraud-check/batches/{batch}/items/{item}/retry', [BulkFraudCheckController::class, 'retry'])
                    ->middleware('throttle:120,1')
                    ->name('fraud-check.batches.retry');
                Route::post('/fraud-check/batches/{batch}/items/{item}/refresh', [BulkFraudCheckController::class, 'refresh'])
                    ->middleware('throttle:30,1')
                    ->name('fraud-check.batches.refresh');
                Route::get('/fraud-check/batches/{batch}/preview', [BulkFraudCheckController::class, 'preview'])
                    ->name('fraud-check.batches.preview');
                Route::get('/fraud-check/batches/{batch}/export', [BulkFraudCheckController::class, 'export'])
                    ->name('fraud-check.batches.export');
            });

            Route::get('/settings/password', [PasswordSettingsController::class, 'edit'])
                ->name('settings.password');
            Route::put('/settings/password', [PasswordSettingsController::class, 'update'])
                ->name('settings.password.update');

            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            Route::get('/websites', [WebsiteController::class, 'index'])
                ->middleware('permission:websites.view')
                ->name('websites');

            Route::get('/billing', [BillingController::class, 'index'])
                ->middleware('permission:billing.view')
                ->name('billing');

            Route::post('/billing/payment-request', [BillingController::class, 'store'])
                ->middleware('permission:billing.manage')
                ->name('billing.payment-request');

            Route::get('/employees', [EmployeeController::class, 'index'])
                ->middleware('permission:employees.view')
                ->name('employees');

            Route::post('/employees', [EmployeeController::class, 'store'])
                ->middleware('permission:employees.manage')
                ->name('employees.store');

            Route::put('/employees/{employee_id}', [EmployeeController::class, 'update'])
                ->middleware('permission:employees.manage')
                ->name('employees.update');

            Route::delete('/employees/{employee_id}', [EmployeeController::class, 'destroy'])
                ->middleware('permission:employees.manage')
                ->name('employees.destroy');
        });
    });
