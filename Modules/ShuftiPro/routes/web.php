<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\ShuftiPro\app\Http\Controllers\ShuftiProController;

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

// Route::group([], function () {
//     Route::resource('shuftipro', ShuftiProController::class)->names('shuftipro');
// });


// Route::any('api/callback/shuftipro', [ShuftiProController::class, 'callback']);


Route::prefix('callback/webhook')->group(function () {
    Route::any("business-verification-callback", [ShuftiProController::class, 'business_callback'])->name("shufti.business.verification.callback");
    Route::any('shuftipro-callback', [ShuftiProController::class, 'webhook']);
    Route::any("callback/shufti/webhook/{quoteId}/{type}", [ShuftiProController::class, 'webhook'])->withoutMiddleware(VerifyCsrfToken::class);
    Route::any("redirect/shufti/callback/", [ShuftiProController::class, 'callback'])->withoutMiddleware(VerifyCsrfToken::class);
})->withoutMiddleware(VerifyCsrfToken::class);