<?php

namespace Modules\Advcash\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use App\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Modules\Advcash\app\Services\AdvCashService;
use Modules\SendMoney\app\Jobs\CompleteSendMoneyJob;
use Modules\SendMoney\app\Models\SendMoney;
use \SoapClient;
use \SoapFault;
use Carbon\Carbon;


class AdvcashController extends Controller
{
    protected $advCashService;

    public function __construct()
    {
        $this->advCashService = new AdvCashService;
    }

    public function initiatePayin($deposit_id, $txn_amount, $currency, $txn_type, $gateway)
    {
        $deposit = [
            "txn_type" => $txn_type,
            "txn_id" => $deposit_id,
            "txn_amount" => $txn_amount,
            "txn_currency" => $currency,
            "user_id" => auth()->id()
        ];

        update_deposit_gateway_id($deposit_id, $deposit_id);
        $checkoutUrl = route('advcash.checkout.url', encrypt($deposit), true);
        return $checkoutUrl;
    }

    public function initiatePayment($amount, $currency)
    {
        $description = "Payout from Yativo";
        $client = new AdvcashController();
        $curl = $client->soap($amount, $currency, $description);
        if (is_string($curl)) {
            if (strpos($curl, 'error') !== false) {
                return ['error' => $curl];
            }
            return [''];
        }

        if (is_object($curl)) {
            $curl = (array) $curl;
        }

        return $curl;
    }

    public function withdrawal($quoteId, $currency, $payoutObject)
    {
        $gateway = (object) [];
        $amount = $payoutObject->amount;
        $description = "Payout from Yativo";
        $curl = $this->initiatePayment($amount, $currency);
        if ($gateway->payment_mode == "advcash_card") {
            $this->sendMoneyToBankCard($amount, $currency, $payoutObject);
        }
        if (is_string($curl)) {
            if (strpos($curl, 'error') !== false) {
                return ['error' => $curl];
            }
            return ['message' => $curl];
        }
    }

    public function handleCallback(Request $request)
    {
        // Extract query parameters from the URL
        $queryParams = $request->all();
        Log::info("Advcash parameters:", ['query_params' => $queryParams]);

        // Extract specific parameters from query
        $amount = $queryParams['ac_amount'] ?? 0;
        $quoteId = $queryParams['ac_order_id'] ?? null;

        if (!$quoteId) {
            http_response_code(200);
            return request()->redirect_url ?? redirect()->to(env('WEB_URL', "https://app.yativo.com"));
        }

        $depo = Deposit::whereId($quoteId)->first()->toArray();
        Log::info("deposit info to be processed", $depo);
        // Process the PayIn transaction
        $order = TransactionRecord::where("transaction_id", $quoteId)->latest()->first();

        if (!$order) {
            Log::error("Transaction record not found for quote ID: {$quoteId}");
            return redirect()->to(request()->redirect_url ?? env('WEB_URL', "https://app.yativo.com"));
        }

        if (strtoupper($queryParams['ac_transaction_status']) == "COMPLETED") {
            Log::channel("deposit_log")->info("Processing AdvCash Deposit webhook", $order->toArray());
            $this->processDeposit($depo['id'], 'deposit');
        }

        http_response_code(200);
        return redirect()->to(request()->redirect_url ?? env('WEB_URL', "https://app.yativo.com"));

        // return response()->json(['message' => 'Callback processed successfully'], 200);
    }

    public function sendMoneyToBankCard($amount, $currency, $payoutObject)
    {
        try {
            $currencies = [
                "USD" => "US Dollar",
                "EUR" => "Euro",
                "RUR" => "Russian Ruble",
                "GBP" => "Pound Sterling",
                "UAH" => "Ukrainian Hryvnia",
                "KZT" => "Kazakhstani Tenge",
                "BRL" => "Brazilian Real",
                "TRY" => "Turkish Lira",
                "VND" => "Vietnamese Dong"
            ];

            if (!array_key_exists($currency, $currencies)) {
                return ['error' => 'Invalid currency'];
            }

            $action = "sendMoneyToBankCard";

            $payload = [
                'note' => $payoutObject->note,
                'amount' => $amount,
                'currency' => $currency,
                'cardNumber' => $payoutObject->cardNumber,
                'expiryMonth' => $payoutObject->expiryMonth,
                'expiryYear' => $payoutObject->expiryYear,
                'cardHolder' => $payoutObject->cardHolder,
                'cardHolderCity' => $payoutObject->cardHolderCity,
                'cardHolderDOB' => $payoutObject->cardHolderDOB,
                'cardHolderCountry' => $payoutObject->cardHolderCountry,
                'savePaymentTemplate' => false,
                'cardHolderMobilePhoneNumber' => $payoutObject->cardHolderMobilePhoneNumber
            ];

            $curl = $this->advCashService->processAdvCashPayout($action, $payload);
            return $curl;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function sendMoneyToEcurrency()
    {
        try {
            $action = "sendMoneyToEcurrency";
            $payload = [
                'amount' => '1.00',
                'currency' => 'RUR',
                'ecurrency' => 'YANDEX_MONEY',
                'receiver' => '410022528972199',
                'note' => 'Some note',
                'savePaymentTemplate' => 'false'
            ];
            $curl = $this->advCashService->processAdvCashPayout($action, $payload);
            return $curl;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function checkout($deposit)
    {
        try {
            $data = decrypt($deposit);
            $deposit = $data; // Deposit::findOrFail(decrypt($deposit_id));
            return view('advcash', compact('deposit'));
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    private function processDeposit($quoteId, $productName)
    {
        Log::notice("AdvCash Webhook for Deposit for: {$quoteId}");
        $where = [
            "transaction_memo" => "payin",
            "transaction_id" => $quoteId
        ];

        $order = TransactionRecord::where($where)->first();
        if ($order) {
            $deposit_services = new DepositService();
            $deposit_services->process_deposit($order->transaction_id);
        } else {
            Log::error("Order with the Provided ID not found!. ID: {$quoteId}");
        }
    }
}
