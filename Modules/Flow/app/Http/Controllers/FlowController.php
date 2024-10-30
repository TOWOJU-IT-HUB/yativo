<?php

namespace Modules\Flow\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Flow\app\Services\FlowServices;
use Modules\SendMoney\app\Models\SendMoney;


class FlowController extends Controller
{
    public function makePayment($quoteId, $amount, $currency)
    {
        try {
            $user = auth()->user();
            $optional = [];
            $optional = json_encode($optional);

            // Prepare the data array

            // if(empty($quoteId)) {
            //     $quoteId = "DEP-".strtoupper(\Str::random(8));
            // }


            $params = [
                "commerceOrder" => $quoteId,
                "subject" => "Wallet topup by {$user->name}",
                "currency" => $currency,
                "amount" => $amount,
                "email" => $user->email,
                "urlConfirmation" => "http://flowosccomerce.tuxpan.com/csepulveda/api2/pay/confirmPay.php",
                "urlReturn" => "http://flowosccomerce.tuxpan.com/csepulveda/api2/pay/resultPay.php",
                "optional" => $optional,
            ];

            $serviceName = "payment/create";
            $flowApi = new FlowServices;
            $response = $flowApi->send($serviceName, $params, "POST");
            if(!empty($response))  $response['redirect'] = $response["url"] . "?token=" . $response["token"];

            if(empty($quoteId)) {
                updateDepositRawData($quoteId, $response);
            } else updateSendMoneyRawData($quoteId, $response);
            return $response;
        } catch (\Throwable $e) {
            echo $e->getCode() . " - " . $e->getMessage();
        }
    }

    public function getStatus(Request $request, $txnId)
    {
        try {
            $order_object = SendMoney::where('quote_id', $request->commerceOrder)->first();
            if(empty($order_object)) {
                // check if order is deposit.
                $order_object = Deposit::where("deposit_id", $txnId)->first();
            }
            $serviceName = "payment/getStatus";
            $flowApi = new FlowServices;

            $params['token'] = $txnId;

            $response = result($flowApi->send($serviceName, $params, "POST"));
        } catch (\Throwable $e) {}
    }

    public function success(Request $request)
    {
        try {
            $order_object = SendMoney::where('quote_id', $request->commerceOrder)->first();
        } catch (\Throwable $e) {}
    }

}
