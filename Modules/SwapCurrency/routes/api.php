<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\SwapCurrency\app\Http\Controllers\SwapCurrencyController;

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

Route::middleware(['auth:api'])->prefix('v1/swap')->name('api.')->group(function () {
    // Route::get('swapcurrency', fn (Request $request) => $request->user())->name('swapcurrency');
    Route::post('init', [SwapCurrencyController::class, 'initiateSwap']); 
    Route::post('process', [SwapCurrencyController::class, 'processSwap']); 
});
