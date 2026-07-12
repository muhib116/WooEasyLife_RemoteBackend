<?php

use App\Http\Controllers\Portal\BillingController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EmployeeController;
use App\Http\Controllers\Portal\ForcePasswordChangeController;
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
