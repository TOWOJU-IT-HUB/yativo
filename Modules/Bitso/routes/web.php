<?php

use Illuminate\Support\Facades\Route;
use Modules\Bitso\app\Http\Controllers\BitsoController;

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
    Route::resource('bitso', BitsoController::class)->names('bitso');
});
