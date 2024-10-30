<?php

use App\Http\Controllers\WalletController;
use Modules\LocalPayments\app\Services\LocalPaymentServices;
use Illuminate\Support\Facades\Route;
use Modules\LocalPayments\app\Http\Controllers\LocalPaymentsController;

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
    // Route::resource('localpayments', LocalPaymentsController::class)->names('localpayments');

    // Route::get('lll', function () {
    //     $local = new LocalPaymentServices();
    //     $reponse = $local->buildPayoutBankTransfer(1000, "BOL", 1, 20121);
    //     return $reponse;
    // });


    // Route::get("rates", [WalletController::class, 'rates']);
    // Route::get('rate', function () {
    //     $local = new WalletController();
    //     $reponse = $local->rates("NGN");
    //     return $reponse;
    // });
});
