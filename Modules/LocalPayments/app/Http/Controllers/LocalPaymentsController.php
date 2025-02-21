<?php

namespace Modules\LocalPayments\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\LocalPayments\app\Services\LocalPaymentServices;
use Towoju5\Localpayments\Localpayments;

class LocalPaymentsController extends Controller
{
    // public function payout($amount, $currency, $beneficiary, $quoteId)
    // {
    //     try {
    //         $local = new Localpayments();
    //         $services = new LocalPaymentServices();
    //         $data = $services->buildPayoutBankTransfer($amount, $currency, $quoteId, $beneficiary);

    //         if(isset($data['error'])) {
    //             return $data;
    //         }
    //         Log::channel('payout_log')->info("Payout payload data:", $data);

    //         $payout = $local->payout()->createPayout($data);
    //         if (!is_array($payout)) {
    //             $payout = json_encode($payout, true);
    //         }

    //         return $payout;
    //     } catch (\Throwable $th) {
    //         return ['error' => $th->getMessage()];
    //     }
    // }

    // public function paymentMethods(Request $request)
    // {
    //     try {
    //         $methods = file_get_contents(public_path('banks/methods.json'));
    //         $dataArray = json_decode($methods, true);

    //         $countryISOCode = $request->country;
    //         $paymentMethod = $request->pay_method; // 
    //         $paymentMethodType = $request->pay_method_type; // bank transfer or cash;

    //         $filteredData = array();
    //         foreach ($dataArray as $item) {
    //             if (
    //                 isset($item['countryISOCode3']) && $item['countryISOCode3'] === $countryISOCode &&
    //                 isset($item[$paymentMethod]) && !empty($item[$paymentMethod])
    //             ) {
    //                 foreach ($item[$paymentMethod] as $method) {
    //                     if ($method['paymentMethodType'] === $paymentMethodType) {
    //                         $filteredData[] = $item;
    //                         break;
    //                     }
    //                 }
    //             }
    //         }

    //         // echo json_encode($filteredData, JSON_PRETTY_PRINT); exit;

    //         // build deposit by cash requirements form
    //         $paymentData = [
    //             "paymentMethod" => [
    //                 "type" => "Cash",
    //                 "code" => "1003",
    //                 "flow" => "DIRECT"
    //             ],
    //             "externalId" => generate_uuid(),
    //             "country" => "ARG",
    //             "amount" => 100.0,
    //             "currency" => "ARS",
    //             "accountNumber" => "032.032.00000001",
    //             "conceptCode" => "0003",
    //             "merchant" => [
    //                 "type" => "COMPANY",
    //                 "name" => "Merchant's name",
    //                 "lastname" => "",
    //                 "document" => [
    //                     "type" => "",
    //                     "id" => ""
    //                 ],
    //                 "email" => ""
    //             ],
    //             "payer" => [
    //                 "type" => "INDIVIDUAL",
    //                 "name" => "Payer's Name",
    //                 "lastname" => "Payer's Last Name",
    //                 "document" => [
    //                     "id" => "99999999",
    //                     "type" => "DNI"
    //                 ],
    //                 "email" => "payer@mail.com"
    //             ]
    //         ];

    //         return $paymentData;
    //     } catch (\Throwable $th) {
    //         //throw $th;
    //     }
    // }

    // public function getBanks(Request $request, $api = true)
    // {
    //     $validate = Validator::make($request->all(), [
    //         'country' => 'required'
    //     ]);

    //     if ($validate->fails()) {
    //         return get_error_response($validate->errors()->toArray());
    //     }

    //     return $response = $this->get_banks__call(strtoupper($request->country));

    //     // Handle successful response
    //     if ($api) {
    //         return get_success_response($response);
    //     } else {
    //         return $response;
    //     }
    // }

    // public function get_banks__call($country)
    // {
    //     $cacheKey = "banks_list_{$country}";
        
    //     return cache()->remember($cacheKey, now()->addHours(24), function () use ($country) {
    //         $local = new Localpayments();
    //         $endpoint = "/api/resources/banks?CountryISOCode3=" . urlencode($country);
            
    //         return $local->curl($endpoint, "get");
    //     });
    // }

    // public static function getPayinAccountNumber($country, $currency, $paymentCode = null)
    // {
    //     $accs = (strtolower(getenv('LOCALPAYMENT_MODE')) === 'test') ? [
    //         "MEX" => "484.484.00000128",
    //         "ARG" => "032.032.00000115",
    //     ] : [
    //         "MEX" => "484.484.00000121",
    //         "ARG" => "032.032.00000115",
    //     ];

    //     $accountNumber = $accs[strtoupper($country)];
    //     return $accountNumber;
    // }
}