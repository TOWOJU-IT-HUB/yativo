<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Route;
use Modules\Advcash\app\Http\Controllers\AdvcashController;

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
    Route::resource('advcash', AdvcashController::class)->names('advcash');

    Route::any('volet/payin/success', [AdvcashController::class, 'handleCallback'])->WithoutMiddleware(VerifyCsrfToken::class);
    Route::any('callback/webhook/advcash', [AdvcashController::class, 'handleCallback'])->WithoutMiddleware(VerifyCsrfToken::class);
    Route::any('webhook/callback/volet/payin/', [AdvcashController::class, 'handleCallback'])->WithoutMiddleware(VerifyCsrfToken::class)->name('advcash.payin.webhook');
});