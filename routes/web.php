<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\FollowUpController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/icons', function(){
        return Inertia::render('Icons/Index');
    })->name('icons');
});
Route::middleware('auth')->group(function () {
    Route::get('/icons', function(){return Inertia::render('Icons/Index');})->name('icons');
    Route::get('/primeicons', function(){return Inertia::render('Icons/Prime');})->name('icons.prime');
    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::group(['as' => 'customers.', 'prefix' => 'customers'], function() {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/save', [CustomerController::class, 'save'])->name('save');
        Route::post('/{id}/delete', [CustomerController::class, 'delete'])->name('delete');
    });
    Route::group(['as' => 'followUp.', 'prefix' => 'follow-up'], function() {
        Route::get('/', [FollowUpController::class, 'index'])->name('index');
        Route::get('/{id}/view', [FollowUpController::class, 'followUp'])->name('view');
        Route::post('/{id}/save', [FollowUpController::class, 'save'])->name('save');
    });
});

Route::get('/send-message', [FollowUpController::class, 'sendMessage']);

require __DIR__.'/auth.php';

// https://inertiaui.com/inertia-tables