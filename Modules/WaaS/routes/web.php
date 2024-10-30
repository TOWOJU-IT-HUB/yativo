<?php

use Illuminate\Support\Facades\Route;
use Modules\WaaS\app\Http\Controllers\WaaSController;

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
    Route::resource('waas', WaaSController::class)->names('waas');
});
