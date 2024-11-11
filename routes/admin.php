<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\Admin\PayinMethodsController;
use App\Http\Controllers\Admin\PayoutMethodsController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;
use Modules\ShuftiPro\app\Http\Controllers\ShuftiProController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('panel', function () {
    return to_route('laratrust.roles.index');
});



// Route::group([], function () {
//     Route::get('login', [AuthController::class, 'showAdminLoginForm'])->name('login');
//     Route::post('process-login', [AuthController::class, 'login'])->name('login.process');
// });

Route::prefix('backoffice')->group(function () {
    Route::get('login', [App\Http\Controllers\Admin\AuthController::class, 'showAdminLoginForm'])->name('admin.login');
    Route::post('login', [App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::get('2fa', [App\Http\Controllers\Admin\AuthController::class, 'show2faForm'])->name('admin.2fa.show');
    Route::post('2fa', [App\Http\Controllers\Admin\AuthController::class, 'loginWith2fa'])->name('admin.2fa.login');

    // Route::domain(env('ADMIN_URL'))->middleware('admin')->group(function () {
    Route::prefix('admin')->as('admin.')->group(function () {
        Route::group([], function () {
            Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        });
        Route::group([], function () {
            Route::prefix('business')->group(function () {
                Route::get('/', [BusinessController::class, 'index'])->name('businesses.index');
                Route::get('show/{id}', [BusinessController::class, 'show'])->name('businesses.show');
            });
        });
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/create', [AdminController::class, 'create'])->name('create');
        Route::post('/store', [AdminController::class, 'store'])->name('store');
        Route::get('/{admin}/edit', [AdminController::class, 'edit'])->name('edit');
        Route::put('/{admin}', [AdminController::class, 'update'])->name('update');
        Route::delete('/{admin}', [AdminController::class, 'destroy'])->name('destroy');

        // Deposit Routes
        Route::group([], function () {
            Route::get('deposits', [DepositController::class, 'index'])->name('deposits.index');
            Route::get('deposits/{id}', [DepositController::class, 'show'])->name('deposits.show');
        });

        Route::group([], function () {
            Route::get('payouts', [DepositController::class, 'index'])->name('payouts.index');
            Route::get('payouts/{id}', [DepositController::class, 'show'])->name('payouts.show');
        });

        Route::resource('exchange_rates', ExchangeRateController::class);
        Route::resource('payin_methods', PayinMethodsController::class);
        Route::resource('payout-methods', PayoutMethodsController::class);
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

        Route::group([], function () {
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
            Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
            Route::delete('activity-logs/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
            Route::post('activity-logs/bulk-delete', [ActivityLogController::class, 'bulkDelete'])->name('activity-logs.bulkDelete');
        });

    });

});
