<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Beneficiary\app\Http\Controllers\BeneficiaryController;
use Modules\Beneficiary\app\Http\Controllers\BeneficiaryPaymentMethodController;

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

Route::middleware(['auth:api', 'kyc_check'])->prefix('v1')->name('api.')->group(function () {
    Route::get('beneficiaries/list', [BeneficiaryController::class, 'index']);
    Route::post('beneficiaries', [BeneficiaryController::class, 'store']);
    Route::get('beneficiaries/{id}', [BeneficiaryController::class, 'show']);
    Route::put('beneficiaries/{id}', [BeneficiaryController::class, 'update']);
    Route::put('beneficiaries/{id}/archive', [BeneficiaryController::class, 'archieve']);
    Route::put('beneficiaries/{id}/unarchive', [BeneficiaryController::class, 'unarchieve']);
    Route::delete('beneficiaries/{id}', [BeneficiaryController::class, 'destroy']);

    Route::group(["prefix" => "beneficiaries/payment-methods", "middleware" => ['auth:api']], function () {
        Route::get("all", [BeneficiaryPaymentMethodController::class, "index"]);
        Route::post("/", [BeneficiaryPaymentMethodController::class, "store"]);
        Route::put("update/{id}",   [BeneficiaryPaymentMethodController::class, "update"]);
        Route::delete("delete/{id}",[BeneficiaryPaymentMethodController::class, "destroy"]);
    });

});
