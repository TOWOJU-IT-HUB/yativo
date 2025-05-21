<?php

use Illuminate\Support\Facades\Route;
use Modules\Bitso\app\Http\Controllers\BitsoController;
use Modules\Bitso\app\Services\BitsoServices;

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

Route::group(['prefix' => 'bitso'], function () {
    Route::resource('bitso', BitsoController::class)->names('bitso');

    Route::get('cop', function() {
        // make a test payout 
        $bitso = new BitsoServices();
        $bitso->requestPath = "/api/v3/funding_details/pse/payment_links";
        $callback_url = request()->redirect_url ?? "https://app.yativo.com";
        $data = [
            "amount" => "12000",
            "cellphone" => "+573103922795",
            "email" => "towojuads+daniela@gmail.com",
            "document_type" => "CC",
            "document_number" => "⁠1053851282",
            "full_name" => "Daniela Aldana Valencia",
            "bank_code" => "007",
            "callback_url" => base64_encode($callback_url)
        ];


        $payload = json_encode($data);
        $result = $bitso->sendRequest($payload, 'POST');
        var_dump([
            "payload" => $data,
            "result" => $result
        ]); exit;
    })
});
