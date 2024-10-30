<?php

use Illuminate\Support\Facades\Route;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;

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

Route::group(['prefix' => 'vitawallet'], function () {
    Route::resource('vitawallet', VitaWalletController::class)->names('vitawallet');
    Route::get('price', [VitaWalletController::class, 'prices'])->name('vitawallet.get.price');
    Route::get('pay', [VitaWalletController::class, 'payin'])->name('vitawallet.get.payin');
    Route::get('withdrawal-rules', [VitaWalletController::class, 'withdrawal_rules'])->name('vitawallet.withdrawal.rules');
    Route::post('create-withdrawal', [VitaWalletController::class, 'create_withdrawal'])->name('vitawallet.create.withdrawal');
});
