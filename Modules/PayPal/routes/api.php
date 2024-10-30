<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\PayPal\app\Http\Controllers\PayPalDepositController;

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

Route::middleware(['auth:api'])->prefix('v1')->name('api.')->group(function () {
    Route::get('paypal', fn (Request $request) => $request->user())->name('paypal');
    
    Route::post('paypal/deposit', [PayPalDepositController::class, 'createOrder'])->name('createOrder');
    Route::get('paypal/capture', [PayPalDepositController::class, 'captureOrder'])->name('captureOrder');
})->middleware('kyc_check');
