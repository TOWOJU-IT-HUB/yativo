<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;

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
    Route::resource('binancepay', BinancePayController::class)->names('binancepay');
});


Route::withoutMiddleware(VerifyCsrfToken::class)->group(function () {
    Route::any('callback/webhook/binance', [BinancePayController::class, 'general_webhook'])->name('binance-pay.general_webhook');
    Route::any('callback/webhook/binance-webhook/{userId}/{type}', [BinancePayController::class, 'webhook'])->name('binance-pay.webhook');
});
