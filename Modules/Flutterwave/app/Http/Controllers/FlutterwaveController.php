<?php

namespace Modules\Flutterwave\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use PhpParser\Node\Stmt\Return_;

class FlutterwaveController extends Controller
{
    public function makePayment(int $quoteId, float $amount, string $currency)
    {
        try {
            //This generates a payment reference
            $reference = Flutterwave::generateReference();
            $user = auth()->user();
            // Enter the details of the payment
            $data = [
                'payment_options' => 'card,banktransfer',
                'amount' => $amount,
                'email' => $user->email,
                'tx_ref' => $reference,
                'currency' => $currency,
                'redirect_url' => route("flutter.callback", [$user->id, $reference]),
                'customer' => [
                    'email' => $user->email,
                    "phone_number" => $user->phone,
                    "name" => $user->name
                ],
                "customizations" => [
                    "title" => getenv('APP_NAME'),
                    "description" => "Payment for trasnsaction $quoteId, on Yativo"
                ]
            ];
            $payment = Flutterwave::initializePayment($data);

            if(!is_array($payment)) {
                $payment = (array)$payment;
            }

            updateSendMoneyRawData($quoteId, $payment);
            if ($payment['status'] !== 'success') {
                // notify something went wrong
                return $payment;
            }
            // return the payment link
            return $payment['data']['link'];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function validatePayment($userId)
    {
        try {
            // return $user = User::findorfail($userId);
            Flutterwave::getTransactionIDFromCallback();
            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $verify = Flutterwave::verifyTransaction($transactionID);

            if(!is_array($verify)){
                return (array)$verify;
            }

            return $verify;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function payout(int $quoteId, float $amount, array $payoutObject)
    {
        try {
            if(isset($payoutObject['raw_data']) AND isset($payoutObject['raw_data']['payment_method_id'])) {
                $beneficiary = BeneficiaryPaymentMethod::whereId($payoutObject['raw_data']['payment_method_id'])->first()->toArray();
            }

            
            $data = [
                "currency" => $payoutObject['currency'],
                "amount" => $amount,
                "narration" => "Payment for trasnsaction ". base64_encode($quoteId).", on Yativo",
            ];

            $fullData = array_merge($data, $beneficiary);
            unset($fullData['id'], $fullData['gateway_id'], $fullData['address'], $fullData['user_id'], $fullData['user_id'], $fullData['nickname']);

            // echo json_encode($fullData); exit;
            $transfer = Flutterwave::transfers()->initiate($fullData);
            if($transfer['status'] =='success') {
                return ['success' => "Transaction in progress you will receive an update shortly"];
            }

            else if($transfer['status'] == 'error') {
                return ['error' => $transfer['message']];
            }
            // return $transfer;
            // return ['success' => "Transaction in progress you will receive an update shortly"];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    /**
     * @param string $source_currency - currency to be paid in
     * @param string $destination_currency - currency to be paid out
     * @param float $amount - amount to be received in the destination currency
     */
    public function payoutRate($source_currency, $destination_currency, $amount)
    {
        //
    }
}


