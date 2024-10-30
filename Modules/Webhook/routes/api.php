<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Webhook\app\Http\Controllers\WebhookController;

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
    Route::get('webhook', fn(Request $request) => $request->user())->name('webhook');
    Route::prefix('business/webhook')->group(function () {
        Route::get('/', [WebhookController::class, 'index']);
        Route::post('/', [WebhookController::class, 'store']);
        Route::put('/{webhook}', [WebhookController::class, 'update']);
        Route::delete('/{webhook}', [WebhookController::class, 'destroy']);
        Route::post('/{webhook}/regenerate-secret', [WebhookController::class, 'regenerateSecret']);
    });
});

