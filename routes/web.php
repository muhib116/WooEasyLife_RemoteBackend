<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PackageHubController;
use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitorController;
use App\Http\Controllers\Analysis\TokenLedgerController;
use App\Http\Controllers\Analysis\UseAnalysisController;
use App\Http\Controllers\CurlController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\PageBuilder;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
| 
im_super=true
*/

Route::get('/', function () {
    return Inertia::render('Welcome3', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/curl', [CurlController::class, 'index']);

// Route::get('app-logo', [PluginsController::class, 'appLogo']);
// Route::get('download-plugins', [PluginsController::class, 'downloadApp']);
// Route::get('get-metadata', [PluginsController::class, 'getMetadata']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/icons', function () {
        return Inertia::render('Icons/Index');
    })->name('icons');
});

Route::middleware('auth')->group(function () {
    Route::post('/fraud-check', [FraudCheckController::class, 'check'])->name('adminFraudCheck');
    Route::post('/fraud-stream', [FraudCheckController::class, 'checkStream'])->name('adminCheckStream');
    Route::get('/icons', function () {
        return Inertia::render('Icons/Index');
    })->name('icons');
    Route::get('/primeicons', function () {
        return Inertia::render('Icons/Prime');
    })->name('icons.prime');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/token-ledger', [TokenLedgerController::class, 'tokenLedger'])->name('tokenLedger');
    Route::any('/get-token-ledger', [TokenLedgerController::class, 'getTokenLedger'])->name('getTokenLedger');

    Route::group(['as' => 'logs.', 'prefix' => 'logs'], function () {
        Route::get('/', [LogController::class, 'index'])->name('index');
        Route::get('/list', [LogController::class, 'listLogs'])->name('list');
        Route::post('/view', [LogController::class, 'viewLog'])->name('view');
        Route::post('/clear-all-log', [LogController::class, 'clearAllLog'])->name('clearAllLog');
    });

    Route::group(['as' => 'sessions.', 'prefix' => 'sessions'], function () {
        Route::get('/', [SessionController::class, 'sessions'])->name('index');
        Route::get('/get-sessions', [SessionController::class, 'getSessions'])->name('getSessions');
        Route::post('/clear-session', [SessionController::class, 'clearSession'])->name('clearSession');
        Route::post('/clear-all-session', [SessionController::class, 'clearAllSession'])->name('clearAllSession');
    });

    Route::group(['as' => 'backups.', 'prefix' => 'backups'], function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('/get-backups', [BackupController::class, 'getBackups'])->name('getBackups');
        Route::post('/dump-database', [BackupController::class, 'dumpDatabase'])->name('dumpDatabase');
        Route::get('/download-backup/{file_name}', [BackupController::class, 'downloadBackup'])->name('downloadBackup');
        Route::post('/delete-file/{file_name}', [BackupController::class, 'deleteFile'])->name('deleteFile');
    });

    Route::group(['as' => 'products.', 'prefix' => 'products'], function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/filter', [ProductController::class, 'filter'])->name('filter');
        Route::post('/save', [ProductController::class, 'save'])->name('save');
        Route::post('/{id}/delete', [ProductController::class, 'delete'])->name('delete');
    });
    Route::group(['as' => 'customers.', 'prefix' => 'customers'], function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/save', [CustomerController::class, 'save'])->name('save');
        Route::post('/{id}/delete', [CustomerController::class, 'delete'])->name('delete');
        Route::post('/filter-customers', [CustomerController::class, 'filter'])->name('filter');
        Route::get('/{id}/address', [CustomerController::class, 'getAddress'])->name('address');
        Route::post('{id}/save-address', [CustomerController::class, 'saveAddress'])->name('saveAddress');
    });
    Route::group(['as' => 'followUp.', 'prefix' => 'follow-up'], function () {
        Route::get('/', [FollowUpController::class, 'index'])->name('index');
        Route::get('/{id}/view', [FollowUpController::class, 'followUp'])->name('view');
        Route::post('/{id}/save', [FollowUpController::class, 'save'])->name('save');
    });

    Route::group(['as' => 'users.', 'prefix' => 'users'], function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/store', [UserController::class, 'store'])->name('store');
        Route::post('approve-sms-recharge/{sms_id}', [UserController::class, 'approveSmsRecharge'])->name('approveSmsRecharge');
        Route::post('reject-sms-recharge/{sms_id}', [UserController::class, 'rejectSmsRecharge'])->name('rejectSmsRecharge');
    });

    Route::group(['as' => 'users.', 'prefix' => 'users/{user_id}'], function () {
        Route::group(['as' => 'business.', 'prefix' => 'business'], function () {
            Route::get('/', [BusinessController::class, 'index'])->name('index');
            Route::post('/store', [BusinessController::class, 'store'])->name('store');
        });
        Route::get('view', [UserController::class, 'view'])->name('view');
        Route::get('api-keys', [UserController::class, 'apiKeys'])->name('apiKeys');
        Route::get('packages', [UserController::class, 'packages'])->name('packages');
        Route::get('packages/{package_id}/use-details', [UserController::class, 'useDetails'])->name('useDetails');
        Route::get('sms-recharge', [UserController::class, 'smsRecharge'])->name('smsRecharge');
        Route::post('sms-admin-recharge', [SmsController::class, 'smsAdminRecharge'])->name('smsAdminRecharge');
        Route::get('sms-use-history', [UserController::class, 'smsUseHistory'])->name('smsUseHistory');
        Route::post('purchase-package', [UserController::class, 'purchase'])->name('purchasePackage');
        Route::post('update-purchase-package', [UserController::class, 'updatePurchasePackage'])->name('updatePurchasePackage');
    });

    Route::group(['as' => 'orders.', 'prefix' => 'orders'], function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        // Route::get('/{id}/view', [FollowUpController::class, 'followUp'])->name('view');
        // Route::post('/{id}/save', [FollowUpController::class, 'save'])->name('save');
    });
    Route::group(['as' => 'builder.', 'prefix' => 'builder'], function () {
        Route::get('/', [PageBuilder::class, 'index'])->name('index');
    });

    Route::group(['as' => 'visitor.', 'prefix' => 'visitor'], function () {
        Route::get('/', [VisitorController::class, 'index'])->name('index');
        Route::get('/visitor/report', [VisitorController::class, 'getRouteHitReport'])->name('report');
    });
    Route::group(['as' => 'useAnalysis.', 'prefix' => 'use-analysis'], function () {
        Route::get('/', [UseAnalysisController::class, 'index'])->name('index');
        Route::post('/get-use-report', [UseAnalysisController::class, 'getUseReport'])->name('getUseReport');
    });

    
    Route::group(['as' => 'frauds.', 'prefix' => 'frauds'], function () {
        Route::get('/', [FraudCheckController::class, 'index'])->name('index');
        Route::get('/expire', [FraudCheckController::class, 'expire'])->name('expire');
        Route::post('/get-expire', [FraudCheckController::class, 'getExpire'])->name('getExpire');
        Route::post('/renew-expire', [FraudCheckController::class, 'renewExpire'])->name('renewExpire');
        Route::post('/check', [FraudCheckController::class, 'check'])->name('check');
    });
    Route::group(['as' => 'apiKeys.', 'prefix' => 'api-keys'], function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::post('/create', [ApiKeyController::class, 'create'])->name('create');
        Route::post('/update/{id}', [ApiKeyController::class, 'update'])->name('update');
        Route::post('/delete/{id}', [ApiKeyController::class, 'delete'])->name('delete');
    });
    Route::group(['as' => 'plugins.', 'prefix' => 'plugins'], function () {
        Route::get('/', [PluginsController::class, 'index'])->name('index');
        Route::post('/create-version', [PluginsController::class, 'createVersion'])->name('createVersion');
        Route::post('/{id}/delete-version', [PluginsController::class, 'deleteVersion'])->name('deleteVersion');
        Route::post('/{id}/update-version', [PluginsController::class, 'updateVersion'])->name('updateVersion');
        Route::get('download-plugins/{version}', [PluginsController::class, 'downloadVersion'])->name('downloadVersion');
    });
    Route::group(['as' => 'packages.', 'prefix' => 'packages'], function () {
        Route::get('/', [PackageHubController::class, 'index'])->name('index');
        Route::post('/create', [PackageHubController::class, 'create'])->name('create');
    });
});

Route::get('/send-message', [FollowUpController::class, 'sendMessage']);

require __DIR__ . '/auth.php';

Route::get('/run-migration', function () {
    Artisan::call('migrate');
    echo 'Success';
});

Route::get('/migration-rollback', function () {
    Artisan::call('migrate:rollback');
    echo 'Rollback successfully';
});


// https://inertiaui.com/inertia-tables

// Http::get()