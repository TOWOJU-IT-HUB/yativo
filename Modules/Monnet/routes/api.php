<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Monnet\app\Http\Controllers\MonnetController;

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
    // Route::get('monnet', fn (Request $request) => $request->user())->name('monnet');
})->middleware('kyc_check');
