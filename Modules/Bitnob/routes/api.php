<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Bitnob\app\Http\Controllers\BitnobController;

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

Route::middleware(['auth:api'])->prefix('v1/virtual')->name('api.')->group(function () {
    Route::get('bitnob', fn (Request $request) => $request->user())->name('bitnob');    
    Route::post('reg-user',              [BitnobController::class, 'reg_user']);
    Route::get('cards',                  [BitnobController::class, 'getCards']);
    Route::post('create-card',           [BitnobController::class, 'createCard'])->middleware('chargeWallet');;
    Route::post('topup-card/{cardId}',   [BitnobController::class, 'topupCard'])->middleware('chargeWallet');;
    Route::get('get-card/{cardId}',      [BitnobController::class, 'getCard']);
    Route::get('transactions/{cardId}',  [BitnobController::class, 'transactions']);

    // supported actions are freeze and unfreeze
    Route::post('freeze-unfreeze/{action}/{cardId}', [BitnobController::class, 'freeze_unfreeze']);
})->middleware('kyc_check');


Route::withoutMiddleware(VerifyCsrfToken::class)->prefix('webhook')->name('bitnob')->group(function () {
    Route::any("callback/bitnob", [BitnobController::class,"handleWebhook"]);
});