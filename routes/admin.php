<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CustomPricingController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\Admin\PayinMethodsController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\PayoutMethodsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Business\PlansController;
use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;
use Laragear\WebAuthn\Http\Routes as WebAuthnRoutes;
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
    // return to_route('laratrust.roles.index');
    return route('webauthn.login');
});



// Route::middleware('guest:admin')->group(function () {
//     // Admin passkey registration
//     Route::post('/admin/passkey/register', [WebAuthnRegisterController::class, 'options'])->name('admin.passkey.register.options');
//     Route::post('/admin/passkey/register/finish', [WebAuthnRegisterController::class, 'store'])->name('admin.passkey.register.finish');

//     // Admin passkey login
//     Route::get('/admin/passkey/login', [WebAuthnLoginController::class, 'options'])->name('admin.passkey.login.options');
//     Route::post('/admin/passkey/login', [WebAuthnLoginController::class, 'store'])->name('admin.passkey.login');
// });




Route::prefix('backoffice')->group(function () {
    Route::get('login', [App\Http\Controllers\Admin\AuthController::class, 'showAdminLoginForm'])->name('admin.login');
    Route::post('login', [App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::get('2fa', [App\Http\Controllers\Admin\AuthController::class, 'show2faForm'])->name('admin.2fa.show');
    Route::post('2fa', [App\Http\Controllers\Admin\AuthController::class, 'loginWith2fa'])->name('admin.2fa.login');

    // Route::domain(env('ADMIN_URL'))->middleware('admin')->group(function () {
    Route::prefix('admin')->as('admin.')
        ->middleware('auth:admin')
        ->group(function () {
            Route::group([], function () {
                Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
            });
            Route::group([], function () {
                Route::prefix('business')->group(function () {
                    Route::get('/', [BusinessController::class, 'index'])->name('businesses.index');
                    Route::get('show/{id}', [BusinessController::class, 'show'])->name('businesses.show');
                    Route::get('approve/{userId}', [BusinessController::class, 'approve_business'])->name('business.approve');
                    Route::post('update-wallet/debit/{userId}', [BusinessController::class, 'manageUserWallet'])->name('business.manage.user.wallet');
                });
            });
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::get('/create', [AdminController::class, 'create'])->name('create');
            Route::post('/store', [AdminController::class, 'store'])->name('store');
            Route::get('/{admin}/edit', [AdminController::class, 'edit'])->name('edit');
            Route::put('/{admin}', [AdminController::class, 'update'])->name('update');
            Route::delete('/{admin}', [AdminController::class, 'destroy'])->name('destroy');

            // Deposit Routes
            Route::group(['prefix' => 'plans'], function () {
                Route::get('/', [PlansController::class, 'plans'])->name('plan.index');
                Route::post('upgrade-customer', [PlansController::class, 'changePlan'])->name('plan.upgrade');
            });

            // Deposit Routes
            Route::group([], function () {
                Route::get('deposits', [DepositController::class, 'index'])->name('deposits.index');
                Route::get('deposits/update-status', [DepositController::class, 'updateStatus'])->name('deposits.update-status');
                Route::get('deposits/{id}', [DepositController::class, 'show'])->name('deposits.show');
            });

            // Custom Pricing controller route
            Route::prefix('custom-pricing')->name('custom-pricing.')->group(function () {
                Route::get('/', [CustomPricingController::class, 'index'])->name('index');
                Route::get('/create', [CustomPricingController::class, 'create'])->name('create');
                Route::post('/', [CustomPricingController::class, 'store'])->name('store');
                Route::delete('/{customPricing}', [CustomPricingController::class, 'destroy'])->name('destroy');
                Route::get('get-gateways', [CustomPricingController::class, 'getGateways'])->name('get.gateways');
            });
 

            Route::group([], function () {
                Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
                Route::get('payouts/{id}', [PayoutController::class, 'show'])->name('payouts.show');
                Route::post('payouts/{id}/reject', [PayoutController::class, 'show'])->name('payouts.reject');
                Route::post('payouts/{id}/accept', [PayoutController::class, 'approvePayout'])->name('payouts.accept');
                Route::post('payouts/{id}/process-manual', [PayoutController::class, 'manual'])->name('payouts.process-manual');
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


            Route::get('clear', function () {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('config:cache');
                Artisan::call('view:clear');
                Artisan::call('route:clear');

                return back()->with('success', 'Cache cleared successfully');
            });
        });
});
