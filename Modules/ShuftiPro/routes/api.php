<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\ShuftiPro\app\Http\Controllers\ShuftiProController;

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
    Route::get('shuftipro', fn (Request $request) => $request->user())->name('shuftipro');

    Route::get('shufti-token', [ShuftiProController::class, 'generateShuftiProToken']);
    Route::post("verify-now", [ShuftiProController::class, 'shuftiPro']);
});
