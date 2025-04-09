<?php

namespace Modules\BinancePay\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Services\DepositService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\BinancePay\app\Models\BinancePay;
use Modules\SendMoney\app\Jobs\CompleteSendMoneyJob;
use Modules\SendMoney\app\Models\SendMoney;

class BinancePayController extends Controller
{
    public function init($quoteId, $amount, $currency, $gateway, $txn_type)
    {
        try {
            $trxId = uuid(30);
            $fee = 0; // self::getfees($amount);

            $quote = strtolower($txn_type) !== 'deposit' ? get_quote_by_id($quoteId) : get_deposit_by_id($quoteId);
            $user = request()->user();

            $payload = [
                'env' => [
                    'terminalType' => 'WEB',
                ],
                'orderTags' => [
                    'ifProfitSharing' => false,
                ],
                'merchantTradeNo' => $trxId,
                'orderAmount' => $amount + $fee,
                'currency' => $currency,
                'goods' => [
                    'goodsType' => '02',
                    'goodsCategory' => 'Z000',
                    'referenceGoodsId' => '7876763A3B',
                    'goodsName' => $txn_type,
                    'goodsDetail' => 'Wallet Topup for customer ' . $user->businessName,
                ],
                'returnUrl' => request()->redirect_url ?? getenv('WEB_URL') . 'dashboard',
            ];

            $call = $this->api_call('/binancepay/openapi/v2/order', 'POST', $payload);

            if ($call["status"] != "FAIL") {
                update_deposit_gateway_id($quoteId, $trxId);
                BinancePay::create([
                    'deposit_id' => $quoteId,
                    'gateway_id' => $trxId, // the transaction ID from BinancePay
                    'trx_type' => $txn_type,
                ]);
            }

            Log::error("Init Binance response", $call);

            return $call['data']['checkoutUrl'] ?? ['error' => 'Unable to initiate deposit action.'];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), 'trace' => $th->getTrace()];
        }
    }

    public static function verifyOrder(string $trxId): array
    {
        $endpoint = "/binancepay/openapi/v2/order/query";
        $payload = ['merchantTradeNo' => $trxId];
        return (new self())->api_call($endpoint, 'POST', $payload);
    }

    public function selfVerifyOrder()
    {
        Log::info(json_encode(['binance_notif', request()->all()]));
        $endpoint = "/binancepay/openapi/v2/order/query";
        $deposits = Deposit::where('deposit_status', 'pending')->get();

        foreach ($deposits as $deposit) {
            $userId = $deposit->user_id;
            $order = Transaction::where("reference", $deposit->deposit_id)->first();
            $verify = self::verifyOrder($deposit->deposit_id);

            if (isset($verify['data']) && $verify['data']['status'] === "PAID" && $deposit->deposit_status === 'pending' && $order->status === 'pending') {
                $order->status = "completed";
                $deposit->deposit_status = 'success';
                $rechargeAmount = floatval($verify['data']['orderAmount'] - $deposit->deposit_fee);
                $order->save();
                $deposit->save();

                Log::info($rechargeAmount);
                // Handle balance update here if needed
            }
        }
    }

    public function withdrawal(int $quoteId, $amount, string $currency)
    {
        try {
            $beneficiary = BeneficiaryPaymentMethod::with('beneficiary')
                ->whereId(request()->payment_method_id)
                ->firstOrFail();

            $wallet_address = $beneficiary->payment_data->email ?? null;
            if (!$wallet_address) {
                throw new \Exception('Invalid wallet address');
            }

            $trxId = $quoteId;
            $user = request()->user();
            $fee = 0; // TODO: Implement getfees($amount, $currency, 'payout');
            $totalAmount = round($amount - $fee, 2);

            if ($totalAmount <= 0) {
                throw new \Exception('Invalid withdrawal amount');
            }

            $payload = [
                'requestId' => $trxId,
                'batchName' => $trxId,
                'currency' => $currency,
                'totalAmount' => $totalAmount,
                'totalNumber' => 1,
                'bizScene' => 'MERCHANT_PAYMENT',
                'transferDetailList' => [
                    [
                        'merchantSendId' => $trxId,
                        'transferAmount' => $totalAmount,
                        'receiveType' => "EMAIL",
                        'transferMethod' => 'FUNDING_WALLET',
                        'receiver' => $wallet_address,
                        'remark' => "Withdrawal via Binance Pay to {$wallet_address} by @$user->email",
                    ],
                ],
            ];

            $call = $this->api_call('/binancepay/openapi/payout/transfer', 'POST', $payload);

            if (!isset($call['data'])) {
                $errorMessage = $call['errorMessage'] ?? 'Unknown error occurred';
                Log::error("Error while making a payout request", [
                    'user_id' => $user->id,
                    'response' => $call,
                    'error' => $errorMessage
                ]);
                return ["error" => $errorMessage];
            }

            // TODO: Save withdrawal request to database

            return [
                "msg" => "Your withdrawal request is received and will be processed soon.",
                "data" => $call['data']
            ];
        } catch (\Exception $e) {
            Log::error("Withdrawal request failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ["error" => "An unexpected error occurred. Please try again later."];
        }
    }

    private function api_call(string $url, string $method, array $request = [])
    {
        // Generate nonce string
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $nonce = '';
        for ($i = 1; $i <= 32; $i++) {
            $pos = mt_rand(0, strlen($chars) - 1);
            $char = $chars[$pos];
            $nonce .= $char;
        }
        $ch = curl_init();
        $timestamp = round(microtime(true) * 1000);
        $json_request = json_encode($request);
        $payload = $timestamp . "\n" . $nonce . "\n" . $json_request . "\n";
        $binance_pay_key = env('binance_pay_key');
        $binance_pay_secret = env('binance_pay_secret');
        $signature = strtoupper(hash_hmac('SHA512', $payload, $binance_pay_secret));
        $headers = [];
        $headers[] = "Content-Type: application/json";
        $headers[] = "BinancePay-Timestamp: $timestamp";
        $headers[] = "BinancePay-Nonce: $nonce";
        $headers[] = "BinancePay-Certificate-SN: $binance_pay_key";
        $headers[] = "BinancePay-Signature: $signature";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, "https://bpay.binanceapi.com" . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $result = ['error' => 'Error:' . curl_error($ch)];
        }
        curl_close($ch);
        $response = result($result);
        return $response;
    }

    public function webhook(Request $request, int $userId, string $type)
    {
        try {
            Log::info('Binance webhook 2', $request->all());
            $data = $request->all();
            $quoteId = $data['merchantTradeNo'] ?? null;
            $order = TransactionRecord::where("transaction_id", $quoteId)->first();
            $send_money = SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->first();

            if ($send_money) {
                CompleteSendMoneyJob::dispatchAfterResponse($quoteId);
            }

            if ($order) {
                $deposit_services = new DepositService();
                $deposit_services->process_deposit($order->transaction_id);
                return response()->json(['message' => 'Order processed successfully'], 200);
            }
            return response()->json(['error' => 'Order not found'], 404);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function general_webhook(Request $request)
    {
        Log::info('Binance incoming webhook', ['method' => $request->method(), 'payload' => $request->data]);

        if (!isset($request->data)) {
            Log::error("Error while processing incoming webhook: " . json_encode($request));
        }

        $data = $request->data;

        if (!is_array($data)) {
            $data = result($request->data);
        }

        $trxId = $data['merchantTradeNo'] ?? null;
        $payId = BinancePay::where('gateway_id', $trxId)->first();

        if (!$payId) {
            Log::error("No Binance pay ID found for ID: $trxId");
            return response()->json(['message' => 'Webhook processed'], 200);
        }

        $quoteId = $payId->deposit_id;
        Log::info('Webhook data', ['incoming array data' => $data, "quoteId" => $quoteId]);

        if (!$quoteId) {
            return response()->json(['message' => 'Webhook processed'], 200);
        }

        $verify = $this->verifyOrder($trxId);
        Log::notice("Order verified ID and retrieved: ", $verify);
        if (isset($verify['data']) && $verify['data']['status'] === "PAID") {
            Log::notice("Webhook verified successfully", $verify);

            $productName = strtolower($data['productName']);
            switch ($productName) {
                case "send_money":
                    $this->processSendMoney($quoteId);
                    break;
                case "deposit":
                    $this->processDeposit($quoteId, $productName);
                    break;
                default:
                    Log::notice("Webhook for unknown product: " . $data['productName']);
            }

        }
        return response()->json(['message' => 'Webhook processed'], 200);
    }

    private function processSendMoney($quoteId)
    {
        Log::notice("Webhook for send money");
        if (SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->exists()) {
            CompleteSendMoneyJob::dispatchAfterResponse($quoteId);
        }
    }

    private function processDeposit($quoteId, $productName)
    {
        Log::notice("Webhook for Deposit for: ". $quoteId);
        $order = TransactionRecord::where("transaction_id", $quoteId)
            ->where('transaction_type', $productName)
            ->first();

        if ($order) {
            $deposit_services = new DepositService();
            $deposit_services->process_deposit($order->id);
        }
    }

    public function get_rate()
    {
        $endpoint = "api/v3/avgPrice";
        $url = "https://api.binance.com/$endpoint?" . http_build_query([
            'symbol' => "BTCUSDT"
        ]);
        $request = Http::get($url)->toJson();
        $data = to_array($request);

        if (!isset($data['price'])) {
            $data['price'] = 1 / 1000;
        }
    }
}
