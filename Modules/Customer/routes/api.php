<?php

use App\Http\Middleware\CustomerKycMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Customer\app\Http\Controllers\CustomerController;
use Modules\Customer\app\Http\Controllers\CustomerVirtualAccountController;
use Modules\Customer\app\Http\Controllers\CustomerVirtualCardsController;
use Modules\Customer\app\Http\Controllers\DojahVerificationController;

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

Route::middleware(['auth:api'])->prefix('v1')->name('api.customer.')->group(function () {
    Route::get('customer', [CustomerController::class, 'index'])->name('customer.list');
    Route::get('customer/{customer_id}', [CustomerController::class, 'show'])->name('customer.get');
    Route::post('customer', [CustomerController::class, 'store'])->name('customer.store');
    Route::put('customer/{customer_id}', [CustomerController::class, 'update'])->name('customer.update');
    Route::delete('customer/{customer_id', [CustomerController::class, 'destroy'])->name('customer.destroy');
});


Route::middleware(['auth:api'])->prefix('v1/customer/virtual')->name('api.')->group(function () {
    Route::post('cards/activate', [CustomerVirtualCardsController::class, 'regUser'])->name('virtual-cards.activate');
    Route::get('cards/list', [CustomerVirtualCardsController::class, 'index'])->name('virtual-cards.list');
    Route::get('cards/get/{card_id}', [CustomerVirtualCardsController::class, 'show'])->name('virtual-cards.get');
    Route::get('cards/transactions/{card_id}', [CustomerVirtualCardsController::class, 'getTransactions'])->name('virtual-cards.transactions');
    Route::post('cards/create', [CustomerVirtualCardsController::class, 'store'])->name('virtual-cards.store');
    Route::post('cards/topup', [CustomerVirtualCardsController::class, 'topUpCard'])->name('virtual-cards.topup');
    Route::put('cards/update/{card_id}', [CustomerVirtualCardsController::class, 'update'])->name('virtual-cards.update'); //freeze and unfreeze card
    Route::delete('cards/{card_id}/delete', [CustomerVirtualCardsController::class, 'destroy'])->name('virtual-cards.destroy');
});

Route::middleware(['auth:api', 'kyc_check'])->prefix('v1/customer/virtual-accounts')->name('api.')->group(function () {
    Route::post('create', [CustomerVirtualAccountController::class, 'initAccountCreation'])->name('vc.customer.account.create');
    Route::get('account', [CustomerController::class, 'index'])->name('vc.customer.list');
    Route::get('account/{customer_id}', [CustomerController::class, 'show'])->name('vc.customer.get');
    Route::put('account/{customer_id}', [CustomerController::class, 'update'])->name('vc.customer.update');
    Route::delete('account/{customer_id}', [CustomerController::class, 'destroy'])->name('vc.customer.destroy');
});


Route::middleware(['auth:api', 'kyc_check'])->prefix('v1/verification/')->name('api.customer.')->group(function () {
    Route::post('verify-customer', [DojahVerificationController::class, 'customerVerification'])->name('customer.verification.customer');
    Route::post('business-search', [DojahVerificationController::class, 'verifyBusiness'])->name('customer.verification.businessSerach');
    Route::post('business-details', [DojahVerificationController::class, 'businessDetails'])->name('customer.verification.businessDetails');
    Route::post('verify-selfie', [DojahVerificationController::class, 'customerSelfie'])->name('customer.verification.customerSelfie');
    Route::post('verify-yativo-customer', [CustomerController::class, 'getCustomerKycLink'])->name('customer.yativo.customer');
})->withoutMiddleware(CustomerKycMiddleware::class);