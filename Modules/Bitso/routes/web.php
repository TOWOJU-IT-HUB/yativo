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
        if(request()->input('doc') && request()->input('doc') == "CC") {
            $data = [
                "amount" => "12000",
                "cellphone" => "+573103922795",
                "email" => "towojuads+daniela@gmail.com",
                "document_type" => "CC",
                "document_number" => "1053851282",
                "full_name" => "Daniela Aldana Valencia",
                "bank_code" => "007",
                "callback_url" => base64_encode($callback_url)
            ];
        } elseif(request()->input('doc') && request()->input('doc') == "test") {
            $data = [
                "amount" => "2000",
                "cellphone" => "+573156289887",
                "email" => "mymail@bitso.com",
                "document_type" => "NIT",
                "document_number" => "9014977087",
                "full_name" => "Jane Doe",
                "bank_code" => "006",
                "callback_url" => "aHR0cHM6Ly9hY21lLmNvbQ"
            ];
        } else {
            $data = [
                "amount" => "12000",
                "cellphone" => "+573103922795",
                "email" => "towojuads+daniela@gmail.com",
                "document_type" => "NIT",
                "document_number" => "9012289786",
                "full_name" => "GALIANO COMPANY",
                "bank_code" => "051",
                "callback_url" => base64_encode($callback_url)
            ];
        }


        $payload = json_encode($data);
        $result = $bitso->sendRequest($payload, 'POST');
        echo response()->json([
            "payload" => $data,
            "result" => $result
        ]); exit;
    });
});
