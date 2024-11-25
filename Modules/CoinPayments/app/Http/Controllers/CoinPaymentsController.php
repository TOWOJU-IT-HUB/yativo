<?php

namespace Modules\CoinPayments\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\CoinPayments\app\Services\CoinpaymentServices;
use Modules\SendMoney\app\Jobs\CompleteSendMoneyJob;
use Modules\SendMoney\app\Models\SendMoney;

class CoinPaymentsController extends Controller
{
    public $coinpayments;

    public function __construct()
    {
        $apiKey = getenv("COINPAYMENT_PRIVATE_KEY");
        $secretKey = getenv("COINPAYMENT_PUBLIC_KEY");
        $this->coinpayments = new CoinpaymentServices($apiKey, $secretKey);
    }

    public function makePayment($quoteId, $amount, $currency2): array
    {
        $request = request();
        $request->merge([
            "crypto" => "USDC"
        ]);

        $currency1 = $request->crypto;
        $buyer_email = $request->user()->email;

        $callback_url = route('coinpayments.callback.deposit', ['quoteId' => $quoteId, "currency" => $currency1, "user" => auth()->id()]);
        $response = $this->coinpayments->CreateTransactionSimple(floatval($amount), $currency1, $currency2, $buyer_email, null, $callback_url);
        if (!is_array($response)) {
            $response = json_encode($response, true);
        }
        // \Log::info(json_encode($response));
        // update_deposit_gateway_id($quoteId, $trxId);
        updateSendMoneyRawData($quoteId, $response);
        return $response ?? [];
    }

    public function depositIpn(Request $request, $quoteId, $currency, $user)
    {
        try {
            Log::info('Coinpayment webhook', $request->all());
            $userObject = User::find($user);
            if ($userObject) {
                $where = [
                    "transaction_id" => $quoteId,
                    "transaction_memo" => "payin"
                ];

                $order = TransactionRecord::where($where)->first();
                // retrieve send money
                // $send_money = SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->first();
        
                // if ($send_money) {
                //     CompleteSendMoneyJob::dispatchAfterResponse($quoteId);
                // }
        
                if ($order) {
                    $deposit_services = new DepositService();
                    $deposit_services->process_deposit($order->transaction_id);
                    return http_response_code(200);
                }
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public function validatePayment($transactionId)
    {
        return $this->coinpayments->status($transactionId);
    }

    public function pay($quoteId, $currency, $payoutObject)
    {
        $beneficiary = BeneficiaryPaymentMethod::whereId(request()->payment_method_id)->first();
        $wallet_address = $beneficiary->payment_data->wallet_address;
        $withdrawal = $this->coinpayments->CreateWithdrawal($payoutObject->amount, $payoutObject->currency, $wallet_address);
        if(isset($withdrawal)) {
            foreach ($withdrawal as $key => $value) {
                if($withdrawal[$key]["error"] == 'ok' && $withdrawal[$key]["status"] == 1) {
                    $withdrawal[$key];
                    break;
                }
            }
        }

        return $withdrawal;
    }

    public function generateAddress($userId)
    {
        $get = [];
        $currency = [
            "USDC.BEP20",
            "USDT.BEP20"
        ];
        foreach ($currency as $curr) {
            $get[] = $this->coinpayments->GetCallbackAddress($curr, $userId);
        }

        return $get;
    }
}

