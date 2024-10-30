<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

// Route::middleware(['auth:api'])->prefix('v1')->name('api.')->group(function () {
//     Route::get('binancepay', fn (Request $request) => $request->user())->name('binancepay');
// });


Route::middleware(['auth:api'])->prefix('v1/binace-pay')->name('api.')->group(function () {
    Route::post('payin', [BinancePayController::class, 'init']);
    Route::get('verify/{orderId}', [BinancePayController::class, 'verifyOrder']);
    Route::post('payout', [BinancePayController::class, 'withdrawal']);
})->middleware('kyc_check');
