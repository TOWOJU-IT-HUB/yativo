<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\LocalPayments\app\Http\Controllers\LocalPaymentsController;

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
    Route::get('localpayments', fn (Request $request) => $request->user())->name('localpayments');

    // Route::get('local/banks', [LocalPaymentsController::class, 'getBanks']); 
})->middleware('kyc_check');
