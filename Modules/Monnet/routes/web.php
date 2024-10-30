<?php

use Illuminate\Support\Facades\Route;
use Modules\Monnet\app\Http\Controllers\MonnetController;

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
    Route::resource('monnet', MonnetController::class)->names('monnet');
    Route::post("webhook/monnet/payin",     [MonnetController::class, 'payin_webhook']);
    Route::post("webhook/monnet/payout",    [MonnetController::class, 'payout_webhook']);

    Route::any('callback/monnet/success/{userId}/{txn}', [MonnetController::class, 'success'])->name("callback.monnet.success");
    Route::any('callback/monnet/failed/{userId}/{txn}', [MonnetController::class, 'failed'])->name("callback.monnet.failed");
});

