<?php

use Illuminate\Support\Facades\Route;
use Modules\Flutterwave\app\Http\Controllers\FlutterwaveController;

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
    Route::resource('flutterwave', FlutterwaveController::class)->names('flutterwave');
    Route::get("callback/flutterwave/deposit/{user_id}/{reference}", [FlutterwaveController::class, 'validatePayment'])->name("flutter.callback");
});
