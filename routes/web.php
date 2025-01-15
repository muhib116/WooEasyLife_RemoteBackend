<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PluginsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\CurlController;
use App\Http\Controllers\FraudCheckController;
use App\Http\Controllers\PageBuilder;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
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
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
Route::get('/curl', [CurlController::class, 'index']);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
    Route::get('/icons', function () {
        return Inertia::render('Icons/Index');
    })->name('icons');
    Route::get('/primeicons', function () {
        return Inertia::render('Icons/Prime');
    })->name('icons.prime');
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
    Route::group(['as' => 'orders.', 'prefix' => 'orders'], function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        // Route::get('/{id}/view', [FollowUpController::class, 'followUp'])->name('view');
        // Route::post('/{id}/save', [FollowUpController::class, 'save'])->name('save');
    });
    Route::group(['as' => 'builder.', 'prefix' => 'builder'], function () {
        Route::get('/', [PageBuilder::class, 'index'])->name('index');
    });
    Route::group(['as' => 'frauds.', 'prefix' => 'frauds'], function () {
        Route::get('/', [FraudCheckController::class, 'index'])->name('index');
        Route::post('/check', [FraudCheckController::class, 'check'])->name('check');
    });
    Route::group(['as' => 'apiKeys.', 'prefix' => 'api-keys'], function () {
        Route::get('/', [ApiKeyController::class, 'index'])->name('index');
        Route::post('/create', [ApiKeyController::class, 'create'])->name('create');
        Route::post('/delete', [ApiKeyController::class, 'delete'])->name('delete');
    });
    Route::group(['as' => 'plugins.', 'prefix' => 'plugins'], function () {
        Route::get('/', [PluginsController::class, 'index'])->name('index');
        Route::post('/create-version', [PluginsController::class, 'createVersion'])->name('createVersion');
        Route::get('download-plugins/{version}', [PluginsController::class, 'downloadVersion'])->name('downloadVersion');
    });
});

Route::get('/send-message', [FollowUpController::class, 'sendMessage']);

require __DIR__ . '/auth.php';

// https://inertiaui.com/inertia-tables

// Http::get()