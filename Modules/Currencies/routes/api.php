<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Currencies\app\Http\Controllers\CurrenciesController;

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
    Route::get('currencies/all', [CurrenciesController::class, 'index'])->name('currencies');
})->middleware('kyc_check');
