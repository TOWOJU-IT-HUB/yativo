<?php

use Illuminate\Support\Facades\Route;
use Modules\Khipu\app\Http\Controllers\KhipuController;
use Modules\Khipu\app\Services\KhipuServices;

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
    // Route::resource('khipu', KhipuController::class)->names('khipu');

    Route::get('khipu/test', function() {
        $khipu = new KhipuServices();
        $init = $khipu->makePayment(generate_uuid(), 1000, "CLP");
        return response()->json($init);
    });
});
