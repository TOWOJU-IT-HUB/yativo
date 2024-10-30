<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\CoinPayments\app\Http\Controllers\CoinPaymentsController;

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

Route::group([], function () {
    // Route::resource('coinpayments', CoinPaymentsController::class)->names('coinpayments');
    Route::any('callback/coinpayments/deposit/{quoteId}/{currency}/{user}', [CoinPaymentsController::class, 'depositIpn'])->name('coinpayments.callback.deposit')->withoutMiddleware(VerifyCsrfToken::class);
});
