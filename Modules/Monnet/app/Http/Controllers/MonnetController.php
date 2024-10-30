<?php

namespace Modules\Monnet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Monnet\app\Services\MonnetServices;
use Modules\Monnify\App\Services\MonnifyService;
use Modules\SendMoney\app\Http\Controllers\SendMoneyController;
use Modules\SendMoney\app\Jobs\CompleteSendMoneyJob;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\SendMoney\app\Models\SendQuote;

class MonnetController extends Controller
{
    public function index()
    {
        // return SendMoney::where()->paginate(15);
    }

    public function payin_webhook(Request $request)
    {
        \Log::info(json_encode(['payin_webhook' => $request->all()]));

        if ($this->validatePayinWebhook($request) === false) {
            die('ok');
        }

        // Retrieve the transaction or return false for transaction not exists
        if ((int) $request->payinStateID === 5) {
            $quoteId = $request->payinMerchantOperationNumber;
            
            $order = TransactionRecord::where("transaction_id", $quoteId)->first();
            // retrieve send money
            $send_money = SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->first();
            $deposit = Deposit::whereId($quoteId)->where('status', 'pending')->first();
            if ( !$deposit ) {
                return response()->json(['error' => 'Deposit not found'], 404);
            }

            if ($send_money) {
                CompleteSendMoneyJob::dispatchAfterResponse($quoteId);
            } 

            if ($order) {
                $deposit_services = new DepositService();
                $deposit_services->process_deposit($order);
                return http_response_code(200);
            }
        }

        return http_response_code(200);
    }

    public function payout_webhook(Request $request)
    {
        \Log::info(json_encode(['payout_webhook' => $request->all()]));

        if (!$this->validateSendMoneyWebhook($request)) {
            die('ok');
        }

        if (!$request->header('verification') || empty($request->header('verification'))) {
            die('ok');
        }

        $body = result($request->all());

        // Retrieve the transaction or return false for transaction not exists
        if (isset($body['payout']) && isset($body['payout']['orderId']) and $body['output']['stage']) {
            $payload = $body['payout'];
            $quoteId = $body['payout']['orderId'];
            $send_money = SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->first();
            if ($send_money) {
                $txn_type = "payin";
                $send_money = SendMoney::whereQuoteId($quoteId)->first();
                $send_money->status = SendMoneyController::SUCCESS;
                $send_money->save();

                $quote = SendQuote::whereId($quoteId)->first();
                $quote->status = SendMoneyController::SUCCESS;
                $quote->save();


                Track::create([
                    "quote_id" => $quoteId,
                    "tracking_status" => "Payout completed successfully",
                    "raw_data" => (array) $payload
                ]);

                // add email notification and fcm notification for complete/successful sendmoney
            } else if ($payout = Withdraw::where('deposit_id', $quoteId)->where('status', 'pending')->first()) // retrieve deposit 
            {
                $txn_type = "payout";
                // complete send money and give value.
                $payout->status = SendMoneyController::SUCCESS;
                $payout->save();
                // add email notification and fcm notification for complete/successful withdrawal
            }

        }

        $txn_record = TransactionRecord::where("transaction_id", $quoteId)->where("transaction_type", $txn_type)->first();
        $txn_record->transaction_payout_details = $payload;
        $txn_record->transaction_status = "completed";
        $txn_record->save();

        return http_response_code(200);
    }

    public function success(Request $request)
    {
        \Log::info(json_encode(['success' => $request->all()]));
        return http_response_code(200);
    }

    public function failed(Request $request)
    {
        \Log::info(json_encode(['failed' => $request->all()]));
        return http_response_code(200);
    }

    public function validateSendMoneyWebhook(Request $request)
    {
        $verification = $request->header('verification');
        $payload = $request->input();

        // Log or store the received notification
        \Log::channel('monnet')->info('Monnet Notification Received', $payload);

        // Perform the verification process (e.g., using RSA)
        $isVerified = $this->verifyMonnetSignature($verification, $payload);

        if ($isVerified) {
            // Process the notification based on its content
            $stage = $payload['output']['stage'] ?? null;

            switch ($stage) {
                case 'SUCCESS':
                    // Handle successful transactions
                    break;
                case 'REJECTED':
                    // Handle rejected transactions
                    break;
                default:
                    // Handle other stages if necessary
                    break;
            }
        } else {
            // Log verification failure
            \Log::channel('monnet')->warning('Monnet Notification Verification Failed', $payload);
        }

        return response()->json(['status' => 'received']);
    }

    private function verifyMonnetSignature($verification, $payload)
    {
        // Path to the public key file
        $publicKeyPath = storage_path('sec/pubkey.pem');

        // Read the public key content
        $publicKeyContent = file_get_contents($publicKeyPath);

        // Create a public key resource
        $publicKey = openssl_pkey_get_public($publicKeyContent);

        if ($publicKey === false) {
            \Log::channel('monnet')->error('Failed to load public key', ['error' => openssl_error_string()]);
            return false;
        }

        // Format the payload into a string
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // Decode the verification header
        $signature = base64_decode($verification);

        // Verify the signature using OpenSSL
        $isVerified = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        // Free the key resource
        openssl_free_key($publicKey);

        if ($isVerified === 1) {
            return true; // Signature is valid
        } elseif ($isVerified === 0) {
            \Log::channel('monnet')->error('Monnet Notification Signature Invalid', ['payload' => $payload]);
        } else {
            \Log::channel('monnet')->error('Monnet Notification Signature Verification Error', ['payload' => $payload, 'error' => openssl_error_string()]);
        }

        return false; // Signature is invalid or verification failed
    }


    private function validatePayinWebhook($request)
    {
        $payinMerchantID = $request->payinMerchantID;
        $keyMonnet = $this->getMonnetSecretKey($payinMerchantID);

        //keyMonnet = Provided for Monnet Payments
        if ($keyMonnet != null) {
            $purchaseVerication = openssl_digest($payinMerchantID .
                $request->payinMerchantOperationNumber . $request->payinAmount .
                $request->payinCurrency . $keyMonnet, 'sha512');

            return $purchaseVerication;
        }

        return false;
    }

    private function getMonnetSecretKey($merchantId)
    {
        $envPrefix = "MONNET_";
        $secretKey = null;
        foreach ($_ENV as $key => $val) {
            if (strpos($key, $envPrefix) === 0 and $val == $merchantId) {
                $secretKey = str_replace("_ID", "", $key);
            }
        }

        if ($secretKey != null) {
            return getenv($secretKey);
        }
    }
}
