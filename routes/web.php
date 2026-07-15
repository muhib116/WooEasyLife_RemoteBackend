<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BlogAiController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerNoticeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatabaseMigrationController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\LandingSettingsController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MarketingSettingsController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\MerchantEmployeeController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderIntelligenceAdminController;
use App\Http\Controllers\Admin\PackageHubController;
use App\Http\Controllers\Admin\PackagePaymentAdminController;
use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SubscriptionAlertAdminController;
use App\Http\Controllers\Admin\SystemMaintenanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitorController;
use App\Http\Controllers\Admin\WebhookActivityController;
use App\Http\Controllers\Admin\WhitelistedDomainController;
use App\Http\Controllers\Analysis\TokenLedgerController;
use App\Http\Controllers\Analysis\UseAnalysisController;
use App\Http\Controllers\App\BlogAnalyticsController;
use App\Http\Controllers\App\BlogController;
use App\Http\Controllers\App\EnglishMarketingController;
use App\Http\Controllers\App\LegalController;
use App\Http\Controllers\App\MarketingSeoController;
use App\Http\Controllers\App\PricingController;
use App\Http\Controllers\App\PublicSubscriptionController;
use App\Http\Controllers\App\RobotsController;
use App\Http\Controllers\App\SitemapController;
use App\Http\Controllers\CurlController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\FraudPartnerCredentialController;
use App\Http\Controllers\PageBuilder;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDownloadGateController;
use App\Http\Controllers\PublicFraudCheckController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SmsController;
use App\Services\LandingPageService;
use App\Services\LandingSettingsService;
use App\Services\PublicSubscriptionService;
use App\Services\SeoMetaService;
use App\Services\SubscriptionPaymentConfigService;
use App\Support\WhatsappLink;
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

Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('public-storage.show');

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/', function () {
    $landingSettings = app(LandingSettingsService::class);
    $landing = app(LandingPageService::class)->payload(request());
    $whatsapp = $landingSettings->adminWhatsapp();
    $subscriptionService = app(PublicSubscriptionService::class);
    $pendingInquiry = $subscriptionService->resolvePendingForVisitor(
        request()->user(),
        request()->session()->get(PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY),
    );

    if (! $pendingInquiry) {
        request()->session()->forget(PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY);
    }

    $seo = app(SeoMetaService::class)->forPage('home');

    return Inertia::render('Welcome3', array_merge($landing, [
        'canLogin' => Route::has('merchant.login'),
        'canRegister' => Route::has('register'),
        'domains' => [],
        'subscriptionWizard' => config('landing.subscription_wizard', []),
        'subscriptionPaymentMethods' => app(SubscriptionPaymentConfigService::class)->forApi(),
        'whatsappSupportUrl' => WhatsappLink::url(
            $whatsapp,
            config('landing.whatsapp_default_message'),
        ),
        'whatsappDisplayPhone' => $landing['whatsappDisplayPhone'] ?? $whatsapp,
        'pendingSubscriptionInquiry' => $pendingInquiry,
        'seo' => $seo,
    ]))->withViewData(['seo' => $seo]);
});

Route::get('/bd-fraud-checker', [MarketingSeoController::class, 'bdFraudChecker'])
    ->name('seo.bd-fraud-checker');
Route::get('/fake-order-protection', [MarketingSeoController::class, 'fakeOrderProtection'])
    ->name('seo.fake-order-protection');
Route::get('/courier-auto-entry', [MarketingSeoController::class, 'courierAutoEntry'])
    ->name('seo.courier-auto-entry');
Route::get('/fraudbd-alternative', [MarketingSeoController::class, 'fraudBdAlternative'])
    ->name('seo.fraudbd-alternative');
Route::get('/pathao-fraud-check', [MarketingSeoController::class, 'courierIntent'])
    ->defaults('courier', 'pathao')
    ->name('seo.pathao-fraud-check');
Route::get('/steadfast-fraud-check', [MarketingSeoController::class, 'courierIntent'])
    ->defaults('courier', 'steadfast')
    ->name('seo.steadfast-fraud-check');
Route::get('/redx-fraud-check', [MarketingSeoController::class, 'courierIntent'])
    ->defaults('courier', 'redx')
    ->name('seo.redx-fraud-check');

Route::get('/ki-vabe-fake-order-atkabo', [MarketingSeoController::class, 'keywordIntent'])
    ->defaults('seoKey', 'ki_vabe_fake_order_atkabo')
    ->name('seo.ki-vabe-fake-order-atkabo');
Route::get('/fake-customer-check', [MarketingSeoController::class, 'keywordIntent'])
    ->defaults('seoKey', 'fake_customer_check')
    ->name('seo.fake-customer-check');
Route::get('/bd-courier-ratio-checker', [MarketingSeoController::class, 'keywordIntent'])
    ->defaults('seoKey', 'bd_courier_ratio_checker')
    ->name('seo.bd-courier-ratio-checker');
Route::get('/fake-order-check', [MarketingSeoController::class, 'keywordIntent'])
    ->defaults('seoKey', 'fake_order_check')
    ->name('seo.fake-order-check');
Route::get('/courier-checker', [MarketingSeoController::class, 'keywordIntent'])
    ->defaults('seoKey', 'courier_checker')
    ->name('seo.courier-checker');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('blog.show');
Route::post('/blog/analytics/event', [BlogAnalyticsController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('blog.analytics.event');

Route::prefix('en')->name('seo.en.')->group(function () {
    Route::get('/', [EnglishMarketingController::class, 'home'])->name('home');
    Route::get('/bd-fraud-checker', [EnglishMarketingController::class, 'bdFraudChecker'])
        ->name('bd-fraud-checker');
    Route::get('/blog', [EnglishMarketingController::class, 'blogIndex'])->name('blog');
});

Route::prefix('public/download-gate')->name('landing.download-gate.')->group(function () {
    Route::post('/send-otp', [PublicDownloadGateController::class, 'sendOtp'])
        ->middleware('throttle:8,1')
        ->name('send-otp');
    Route::post('/verify-otp', [PublicDownloadGateController::class, 'verifyOtp'])
        ->middleware('throttle:20,1')
        ->name('verify-otp');
    Route::post('/validate-website', [PublicDownloadGateController::class, 'validateWebsite'])
        ->middleware('throttle:30,1')
        ->name('validate-website');
    Route::get('/download/{asset}', [PublicDownloadGateController::class, 'download'])
        ->where('asset', 'apk|plugin')
        ->middleware('throttle:30,1')
        ->name('download');
});

Route::prefix('public/fraud-check')->name('landing.fraud-check.')->group(function () {
    Route::get('/stats', [PublicFraudCheckController::class, 'stats'])
        ->name('stats');
    Route::post('/', [PublicFraudCheckController::class, 'check'])
        ->middleware('throttle:30,1')
        ->name('check');
});

Route::get('/pricing', PricingController::class)->name('pricing');
Route::post('/pricing/subscribe/validate', [PublicSubscriptionController::class, 'validateFields'])
    ->middleware('throttle:120,1')
    ->name('pricing.subscribe.validate');
Route::post('/pricing/subscribe/lead', [PublicSubscriptionController::class, 'saveLead'])
    ->middleware('throttle:30,1')
    ->name('pricing.subscribe.lead');
Route::post('/pricing/subscribe', [PublicSubscriptionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('pricing.subscribe');

Route::prefix('wooeasylife/app')->name('wooeasylife.app.')->group(function () {
    Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])
        ->name('privacy-policy');
    Route::get('/terms-of-service', [LegalController::class, 'termsOfService'])
        ->name('terms-of-service');
});

Route::prefix('woodnutsbolts')->name('woodnutsbolts.')->group(function () {
    Route::get('/privacy-policy', [LegalController::class, 'woodnutsboltsPrivacyPolicy'])
        ->name('privacy-policy');
    Route::get('/terms-of-service', [LegalController::class, 'woodnutsboltsTermsOfService'])
        ->name('terms-of-service');
});

Route::get('/curl', [CurlController::class, 'index']);

// Route::get('app-logo', [PluginsController::class, 'appLogo']);
// Route::get('download-plugins', [PluginsController::class, 'downloadApp']);
// Route::get('get-metadata', [PluginsController::class, 'getMetadata']);

Route::middleware(['auth', 'auth.active', 'platform.admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/fraud-check', [FraudCheckController::class, 'check'])->name('adminFraudCheck');
    Route::post('/fraud-stream', [FraudCheckController::class, 'checkStream'])->name('adminCheckStream');
    Route::get('/icons', function () {
        return Inertia::render('Icons/Index');
    })->name('icons');
    Route::get('/primeicons', function () {
        return Inertia::render('Icons/Prime');
    })->name('icons.prime');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
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

    Route::group(['as' => 'backups.', 'prefix' => 'backups', 'middleware' => 'permission:roles.manage'], function () {
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

    Route::group(['as' => 'migrations.', 'prefix' => 'migrations', 'middleware' => 'permission:roles.manage'], function () {
        Route::get('/', [DatabaseMigrationController::class, 'index'])->name('index');
        Route::get('/status', [DatabaseMigrationController::class, 'status'])->name('status');
        Route::post('/run', [DatabaseMigrationController::class, 'migrate'])->name('run');
        Route::post('/rollback', [DatabaseMigrationController::class, 'rollback'])->name('rollback');
        Route::post('/seed', [DatabaseMigrationController::class, 'seed'])->name('seed');
    });

    Route::group(['as' => 'maintenance.', 'prefix' => 'maintenance', 'middleware' => 'permission:roles.manage'], function () {
        Route::get('/', [SystemMaintenanceController::class, 'index'])->name('index');
        Route::get('/status', [SystemMaintenanceController::class, 'status'])->name('status');
        Route::post('/run', [SystemMaintenanceController::class, 'run'])->name('run');
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
        Route::post('websites/update', [UserController::class, 'updateWebsite'])->name('websites.update');
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

    Route::group(['as' => 'orderIntelligence.', 'prefix' => 'order-intelligence', 'middleware' => 'permission:dashboard.view'], function () {
        Route::get('/', [OrderIntelligenceAdminController::class, 'index'])->name('index');
        Route::get('/customers', [OrderIntelligenceAdminController::class, 'customers'])->name('customers');
        Route::get('/orders', [OrderIntelligenceAdminController::class, 'orders'])->name('orders');
        Route::get('/records', [OrderIntelligenceAdminController::class, 'records'])->name('records');
        Route::get('/api-docs', [OrderIntelligenceAdminController::class, 'apiDocs'])->name('apiDocs');
        Route::get('/merchants/{accessTokenId}/dashboard', [OrderIntelligenceAdminController::class, 'merchantDashboard'])->name('merchantDashboard');
        Route::get('/data/customers', [OrderIntelligenceAdminController::class, 'customersList'])->name('customersList');
        Route::get('/data/customer-lookup', [OrderIntelligenceAdminController::class, 'customerLookup'])->name('customerLookup');
        Route::get('/data/orders', [OrderIntelligenceAdminController::class, 'ordersList'])->name('ordersList');
        Route::get('/data/records/{table}', [OrderIntelligenceAdminController::class, 'recordsTable'])->name('recordsTable');
        Route::post('/reindex-search', [OrderIntelligenceAdminController::class, 'reindexSearch'])->name('reindexSearch');
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
        Route::post('/{id}/restore', [PackageHubController::class, 'restore'])->name('restore');
        Route::post('/{id}/toggle-status', [PackageHubController::class, 'toggleStatus'])->name('toggleStatus');
    });

    Route::group(['as' => 'packagePayments.', 'prefix' => 'package-payments'], function () {
        Route::get('/', [PackagePaymentAdminController::class, 'index'])
            ->middleware('permission:payments.view')
            ->name('index');
    });

    Route::group(['as' => 'orders.', 'prefix' => 'orders'], function () {
        Route::get('/', [OrderAdminController::class, 'index'])
            ->middleware('permission:payments.view')
            ->name('index');
        Route::get('/{order}', [OrderAdminController::class, 'show'])
            ->middleware('permission:payments.view')
            ->name('show');
        Route::post('/{order}/status', [OrderAdminController::class, 'updateStatus'])
            ->middleware('permission:payments.approve')
            ->name('updateStatus');
        Route::get('/{order}/convert-preview', [OrderAdminController::class, 'convertPreview'])
            ->middleware('permission:payments.approve')
            ->name('convertPreview');
        Route::post('/{order}/convert', [OrderAdminController::class, 'convert'])
            ->middleware('permission:payments.approve')
            ->name('convert');
        Route::post('/{order}/reveal-license', [OrderAdminController::class, 'revealLicense'])
            ->middleware('permission:payments.approve')
            ->name('revealLicense');
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

    Route::group(['as' => 'customerNotices.', 'prefix' => 'customer-notices'], function () {
        Route::get('/', [CustomerNoticeController::class, 'index'])->name('index');
        Route::post('/', [CustomerNoticeController::class, 'store'])->name('store');
        Route::put('/{customerNotice}', [CustomerNoticeController::class, 'update'])->name('update');
        Route::delete('/{customerNotice}', [CustomerNoticeController::class, 'destroy'])->name('destroy');
    });

    Route::group([
        'as' => 'mediaLibrary.',
        'prefix' => 'media-library',
        'middleware' => 'permission:billing.manage',
    ], function () {
        Route::get('/', [MediaLibraryController::class, 'index'])->name('index');
        Route::get('/list', [MediaLibraryController::class, 'list'])->name('list');
        Route::post('/', [MediaLibraryController::class, 'store'])->name('store');
        Route::post('/fetch-url', [MediaLibraryController::class, 'fetchUrl'])->name('fetchUrl');
        Route::put('/{mediaItem}', [MediaLibraryController::class, 'update'])->name('update');
        Route::delete('/{mediaItem}', [MediaLibraryController::class, 'destroy'])->name('destroy');
    });

    Route::group(['as' => 'blogPosts.', 'prefix' => 'blog-posts'], function () {
        Route::get('/', [BlogPostController::class, 'index'])->name('index');
        Route::get('/create', [BlogPostController::class, 'create'])->name('create');
        Route::post('/', [BlogPostController::class, 'store'])->name('store');
        Route::post('/upload-image', [BlogPostController::class, 'uploadImage'])->name('uploadImage');
        Route::get('/{blogPost}/edit', [BlogPostController::class, 'edit'])->name('edit');
        Route::put('/{blogPost}', [BlogPostController::class, 'update'])->name('update');
        Route::delete('/{blogPost}', [BlogPostController::class, 'destroy'])->name('destroy');
    });

    Route::group([
        'as' => 'blogAi.',
        'prefix' => 'blog-posts/ai',
        'middleware' => ['permission:billing.manage', 'throttle:30,1'],
    ], function () {
        Route::get('/options', [BlogAiController::class, 'options'])->name('options');
        Route::post('/suggest-keywords', [BlogAiController::class, 'suggestKeywords'])->name('suggestKeywords');
        Route::post('/auto', [BlogAiController::class, 'startAuto'])->name('auto');
        Route::get('/runs/{blogAiRun}', [BlogAiController::class, 'showRun'])->name('runs.show');
        Route::post('/runs/{blogAiRun}/cancel', [BlogAiController::class, 'cancelRun'])->name('runs.cancel');
        Route::post('/sessions', [BlogAiController::class, 'store'])->name('store');
        Route::get('/sessions/{blogAiSession}', [BlogAiController::class, 'show'])->name('show');
        Route::post('/sessions/{blogAiSession}/recover', [BlogAiController::class, 'recover'])->name('recover');
        Route::post('/sessions/{blogAiSession}/research', [BlogAiController::class, 'research'])->name('research');
        Route::post('/sessions/{blogAiSession}/hooks', [BlogAiController::class, 'hooks'])->name('hooks');
        Route::post('/sessions/{blogAiSession}/outline', [BlogAiController::class, 'outline'])->name('outline');
        Route::post('/sessions/{blogAiSession}/draft', [BlogAiController::class, 'draft'])->name('draft');
        Route::post('/sessions/{blogAiSession}/image', [BlogAiController::class, 'image'])->name('image');
        Route::post('/sessions/{blogAiSession}/image/regenerate', [BlogAiController::class, 'regenerateImage'])->name('image.regenerate');
        Route::post('/sessions/{blogAiSession}/image/approve', [BlogAiController::class, 'approveImage'])->name('image.approve');
    });

    Route::group([
        'as' => 'landingSettings.',
        'prefix' => 'landing-settings',
        'middleware' => 'permission:billing.manage',
    ], function () {
        Route::get('/', [LandingSettingsController::class, 'index'])->name('index');
        Route::put('/', [LandingSettingsController::class, 'update'])->name('update');
    });

    Route::group([
        'as' => 'marketingSettings.',
        'prefix' => 'marketing-settings',
        'middleware' => 'permission:billing.manage',
    ], function () {
        Route::get('/', [MarketingSettingsController::class, 'index'])->name('index');
        Route::put('/', [MarketingSettingsController::class, 'update'])->name('update');
    });

    // Obfuscated path kept for bookmarks; auth + platform.admin are required.
    Route::group(['as' => 'frauds.', 'prefix' => 'q8w1d9zp7kuo2vrb5m6cnx0ahjls4et3ifyugpdbq2m1vnz0l'], function () {
        Route::get('/', [FraudCheckController::class, 'index'])->name('index');
        Route::get('/expire', [FraudCheckController::class, 'expire'])->name('expire');
        Route::post('/save-steadfast-curl', [FraudCheckController::class, 'saveSteadfastCurl'])->name('saveSteadfastCurl');
        Route::post('/fraud-check', [FraudCheckController::class, 'check'])->name('adminFraudCheck');
        Route::post('/get-expire', [FraudCheckController::class, 'getExpire'])->name('getExpire');
        Route::post('/renew-expire', [FraudCheckController::class, 'renewExpire'])->name('renewExpire');
        Route::post('/expire-session', [FraudCheckController::class, 'expireSession'])->name('expireSession');
        Route::post('/check', [FraudCheckController::class, 'check'])->name('check');
        Route::get('/runtime-config', [FraudCheckController::class, 'runtimeConfig'])->name('runtimeConfig');
        Route::put('/runtime-config', [FraudCheckController::class, 'updateRuntimeConfig'])->name('updateRuntimeConfig');
        Route::post('/runtime-config/reset', [FraudCheckController::class, 'resetRuntimeConfig'])->name('resetRuntimeConfig');

        Route::get('/credentials', [FraudPartnerCredentialController::class, 'index'])->name('credentials');
        Route::post('/credentials', [FraudPartnerCredentialController::class, 'store'])->name('credentials.store');
        Route::put('/credentials/{credential}', [FraudPartnerCredentialController::class, 'update'])->name('credentials.update');
        Route::delete('/credentials/{credential}', [FraudPartnerCredentialController::class, 'destroy'])->name('credentials.destroy');
    });
});

require __DIR__.'/portal.php';
require __DIR__.'/auth.php';

// Production deploy (no terminal): POST with X-Deploy-Secret header (secret must not appear in the URL)
Route::post('/deploy', [DeployController::class, 'deploy'])->name('deploy');
// First-time setup only — also requires DEPLOY_ALLOW_SETUP=true
Route::post('/deploy/setup', [DeployController::class, 'setup'])->name('deploy.setup');
