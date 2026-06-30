<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\App\LegalController;
use App\Http\Controllers\App\PricingController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\MerchantEmployeeController;
use App\Http\Controllers\Admin\PackagePaymentAdminController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\SubscriptionAlertAdminController;
use App\Http\Controllers\Admin\PackageHubController;
use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitorController;
use App\Http\Controllers\Admin\WebhookActivityController;
use App\Http\Controllers\Admin\WhitelistedDomainController;
use App\Http\Controllers\Analysis\TokenLedgerController;
use App\Http\Controllers\Analysis\UseAnalysisController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\CurlController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\PageBuilder;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsController;
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
    $landing = app(\App\Services\LandingPageService::class)->payload(request());

    return Inertia::render('Welcome3', array_merge($landing, [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]));
});

Route::prefix('public/fraud-check')->name('landing.fraud-check.')->group(function () {
    Route::get('/stats', [\App\Http\Controllers\PublicFraudCheckController::class, 'stats'])
        ->name('stats');
    Route::post('/', [\App\Http\Controllers\PublicFraudCheckController::class, 'check'])
        ->middleware('throttle:30,1')
        ->name('check');
});

Route::get('/pricing', PricingController::class)->name('pricing');

Route::prefix('wooeasylife/app')->name('wooeasylife.app.')->group(function () {
    Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])
        ->name('privacy-policy');
    Route::get('/terms-of-service', [LegalController::class, 'termsOfService'])
        ->name('terms-of-service');
});

Route::get('/curl', [CurlController::class, 'index']);

// Route::get('app-logo', [PluginsController::class, 'appLogo']);
// Route::get('download-plugins', [PluginsController::class, 'downloadApp']);
// Route::get('get-metadata', [PluginsController::class, 'getMetadata']);

Route::middleware(['auth', 'auth.active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/icons', function () {
        return Inertia::render('Icons/Index');
    })->name('icons');
});

Route::middleware(['auth', 'auth.active', 'platform.admin'])->group(function () {
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
        Route::get('/schedule', [LogController::class, 'schedule'])->name('schedule');
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
        Route::get('/server-requirements', [BackupController::class, 'serverRequirements'])->name('serverRequirements');
        Route::post('/dump-database', [BackupController::class, 'dumpDatabase'])->name('dumpDatabase');
        Route::post('/upload-import', [BackupController::class, 'uploadImport'])->name('uploadImport');
        Route::post('/start-import', [BackupController::class, 'startImport'])->name('startImport');
        Route::post('/import-backup/{file_name}', [BackupController::class, 'importFromBackup'])->name('importFromBackup');
        Route::get('/import-status/{import_id}', [BackupController::class, 'importStatus'])->name('importStatus');
        Route::get('/download-backup/{file_name}', [BackupController::class, 'downloadBackup'])->name('downloadBackup');
        Route::post('/delete-file/{file_name}', [BackupController::class, 'deleteFile'])->name('deleteFile');
    });

    Route::group(['as' => 'webhooks.', 'prefix' => 'webhooks'], function () {
        Route::get('/', [WebhookActivityController::class, 'index'])->name('index');
        Route::get('/summary', [WebhookActivityController::class, 'summary'])->name('summary');
        Route::get('/events', [WebhookActivityController::class, 'events'])->name('events');
        Route::delete('/events', [WebhookActivityController::class, 'deleteEvents'])->name('deleteEvents');
        Route::get('/retries', [WebhookActivityController::class, 'retries'])->name('retries');
        Route::delete('/retries', [WebhookActivityController::class, 'deleteRetries'])->name('deleteRetries');
        Route::post('/process-retries', [WebhookActivityController::class, 'processRetries'])->name('processRetries');
        Route::post('/retries/{retry}/retry', [WebhookActivityController::class, 'retryForward'])->name('retryForward');
        Route::post('/events/{event}/retry', [WebhookActivityController::class, 'retryEvent'])->name('retryEvent');
        Route::post('/events/{event}/test-plugin', [WebhookActivityController::class, 'testPluginReach'])->name('testPlugin');
        Route::post('/test-webhook', [WebhookActivityController::class, 'testCourierWebhook'])->name('testWebhook');
        Route::post('/test-steadfast', [WebhookActivityController::class, 'testSteadfastWebhook'])->name('testSteadfast');
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
        Route::get('/trashed', [UserController::class, 'trashed'])->name('trashed');
        Route::post('/store', [UserController::class, 'store'])->name('store');
        Route::post('/{user_id}/restore', [UserController::class, 'restore'])->name('restore');
        Route::delete('/{user_id}/force', [UserController::class, 'forceDestroy'])->name('forceDestroy');
        Route::delete('/{user_id}', [UserController::class, 'destroy'])->name('destroy');
        Route::post('approve-sms-recharge/{sms_id}', [UserController::class, 'approveSmsRecharge'])
            ->middleware('permission:payments.approve')
            ->name('approveSmsRecharge');
        Route::post('reject-sms-recharge/{sms_id}', [UserController::class, 'rejectSmsRecharge'])
            ->middleware('permission:payments.approve')
            ->name('rejectSmsRecharge');
        Route::post('approve-package-payment/{payment_id}', [PackagePaymentAdminController::class, 'approve'])
            ->middleware('permission:payments.approve')
            ->name('approvePackagePayment');
        Route::post('reject-package-payment/{payment_id}', [PackagePaymentAdminController::class, 'reject'])
            ->middleware('permission:payments.approve')
            ->name('rejectPackagePayment');
    });

    Route::group(['as' => 'users.', 'prefix' => 'users/{user_id}'], function () {
        Route::group(['as' => 'business.', 'prefix' => 'business'], function () {
            Route::get('/', [BusinessController::class, 'index'])->name('index');
            Route::post('/store', [BusinessController::class, 'store'])->name('store');
        });
        Route::get('view', [UserController::class, 'view'])->name('view');
        Route::get('setup', [UserController::class, 'setup'])->name('setup');
        Route::post('setup/validate-domain', [UserController::class, 'validateSetupDomain'])->name('setup.validateDomain');
        Route::post('setup/generate-license', [UserController::class, 'setupGenerateLicense'])->name('setup.generateLicense');
        Route::get('websites', [UserController::class, 'websites'])->name('websites');
        Route::post('websites/delete', [UserController::class, 'destroyWebsite'])->name('websites.delete');
        Route::get('api-keys', [UserController::class, 'apiKeys'])->name('apiKeys');
        Route::get('packages', [UserController::class, 'packages'])->name('packages');
        Route::get('sms', [UserController::class, 'sms'])->name('sms');
        Route::get('sms-recharge', [UserController::class, 'smsRecharge'])->name('smsRecharge');
        Route::post('sms-admin-recharge', [SmsController::class, 'smsAdminRecharge'])->name('smsAdminRecharge');
        Route::get('sms-use-history', [UserController::class, 'smsUseHistory'])->name('smsUseHistory');
        Route::get('billing', [PackagePaymentAdminController::class, 'userBilling'])
            ->middleware('permission:billing.view')
            ->name('billing');
        Route::post('billing/payment-request', [PackagePaymentAdminController::class, 'adminCreate'])
            ->middleware('permission:billing.approve')
            ->name('billing.create');
        Route::get('employees', [MerchantEmployeeController::class, 'index'])
            ->middleware('permission:employees.view')
            ->name('employees');
        Route::post('employees', [MerchantEmployeeController::class, 'store'])
            ->middleware('permission:employees.manage')
            ->name('employees.store');
        Route::put('employees/{employee_id}', [MerchantEmployeeController::class, 'update'])
            ->middleware('permission:employees.manage')
            ->name('employees.update');
        Route::delete('employees/{employee_id}', [MerchantEmployeeController::class, 'destroy'])
            ->middleware('permission:employees.manage')
            ->name('employees.destroy');
        Route::post('purchase-package', [UserController::class, 'purchase'])->name('purchasePackage');
        Route::post('update-purchase-package', [UserController::class, 'updatePurchasePackage'])->name('updatePurchasePackage');
        Route::post('renew-subscription', [UserController::class, 'renewSubscription'])->name('renewSubscription');
        Route::post('change-subscription', [UserController::class, 'changeSubscription'])->name('changeSubscription');
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



    Route::group(['as' => 'developer.', 'prefix' => 'developer'], function () {
        Route::get('/', [DeveloperController::class, 'index'])->name('index');
        Route::post('/proxy', [DeveloperController::class, 'proxy'])->name('proxy');
    });

    Route::group(['as' => 'apiKeys.', 'prefix' => 'api-keys'], function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::post('/create', [ApiKeyController::class, 'create'])->name('create');
        Route::post('/reveal/{id}', [ApiKeyController::class, 'reveal'])->name('reveal');
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
        Route::post('/{id}/update', [PackageHubController::class, 'update'])->name('update');
        Route::post('/{id}/delete', [PackageHubController::class, 'destroy'])->name('delete');
    });

    Route::group(['as' => 'packagePayments.', 'prefix' => 'package-payments'], function () {
        Route::get('/', [PackagePaymentAdminController::class, 'index'])
            ->middleware('permission:payments.view')
            ->name('index');
    });

    Route::group(['as' => 'subscriptionAlerts.', 'prefix' => 'subscription-alerts'], function () {
        Route::get('/', [SubscriptionAlertAdminController::class, 'index'])
            ->middleware('permission:billing.view')
            ->name('index');
    });

    Route::group(['as' => 'roles.', 'prefix' => 'roles', 'middleware' => 'permission:roles.manage'], function () {
        Route::get('/', [RoleAdminController::class, 'index'])->name('index');
        Route::post('/admins/{user_id}/assign', [RoleAdminController::class, 'assignAdminRole'])->name('assignAdmin');
        Route::post('/{role_id}/permissions', [RoleAdminController::class, 'syncPermissions'])->name('syncPermissions');
    });

    Route::group(['as' => 'whitelistedDomains.', 'prefix' => 'whitelisted-domains'], function () {
        Route::get('/', [WhitelistedDomainController::class, 'index'])->name('index');
        Route::post('/', [WhitelistedDomainController::class, 'store'])->name('store');
        Route::put('/{whitelistedDomain}', [WhitelistedDomainController::class, 'update'])->name('update');
        Route::delete('/{whitelistedDomain}', [WhitelistedDomainController::class, 'destroy'])->name('destroy');
    });
});

Route::group(['as' => 'frauds.', 'prefix' => 'q8w1d9zp7kuo2vrb5m6cnx0ahjls4et3ifyugpdbq2m1vnz0l'], function () {
    Route::get('/', [FraudCheckController::class, 'index'])->name('index');
    Route::get('/expire', [FraudCheckController::class, 'expire'])->name('expire');
    Route::post('/save-steadfast-curl', [FraudCheckController::class, 'saveSteadfastCurl'])->name('saveSteadfastCurl');
    Route::post('/fraud-check', [FraudCheckController::class, 'check'])->name('adminFraudCheck');
    Route::post('/get-expire', [FraudCheckController::class, 'getExpire'])->name('getExpire');
    Route::post('/renew-expire', [FraudCheckController::class, 'renewExpire'])->name('renewExpire');
    Route::post('/check', [FraudCheckController::class, 'check'])->name('check');
});

Route::get('/send-message', [FollowUpController::class, 'sendMessage']);

require __DIR__ . '/portal.php';
require __DIR__ . '/auth.php';

Route::get('/get-ip', function () {
    return request()->ip();
});

Route::get('/run-migration', function () {
    Artisan::call('migrate');
    echo 'Success';
});

Route::get('/migration-rollback', function () {
    Artisan::call('migrate:rollback');
    echo 'Rollback successfully';
});

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');    
    echo 'Cache cleared successfully';
});

Route::get('/clear-route', function () {
    Artisan::call('route:clear');
    echo 'Route cleared successfully';
});

Route::get('/deploy/{secret}/setup', [DeployController::class, 'setup'])->name('deploy.setup');

// https://inertiaui.com/inertia-tables

// Http::get()