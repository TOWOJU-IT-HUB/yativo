<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Transak\app\Http\Controllers\TransakController;

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

Route::middleware(['auth:api'])->prefix('v1/transak')->name('api.')->group(function () {
    // Route::get('/', fn (Request $request) => $request->user())->name('transak');
    Route::post('validate-wallet', [TransakController::class,'validateWalletAddress'])->name('wallet.validate');
})->middleware('kyc_check');
