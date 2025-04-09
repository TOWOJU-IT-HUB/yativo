<?php

namespace App\Http\Controllers;

use App\Models\Business\VirtualAccount;
use App\Models\Deposit;
use App\Services\BrlaDigitalService;
use Illuminate\Http\Request;

class BrlaController extends Controller
{
    private $brlaService;

    public function __construct(BrlaDigitalService $brlaService)
    {
        $this->brlaService = $brlaService;
    }

    public function generatePayInBRCode()
    {
        return $this->brlaService->generatePayInBRCode();
    }

    public function getPayInHistory()
    {
        return $this->brlaService->getPayInHistory();
    }

    /**
     * @param string token
     * @param string markupAddress
     * @param string receiverAddress
     * @param string externalId
     * 
     * @return mixed
     */
    public function closePixToUSDDeal($data)
    {
        return $this->brlaService->closePixToUSDDeal($data);
    }

    /**
     * @param string token
     * @param string receiverAddress
     * @param string markupAddress
     * @param string externalId
     * @param string enforceAtomicSwap
     * 
     * @return mixed
     */
    public function convertCurrencies($data)
    {
        return $this->brlaService->convertCurrencies($data);
    }

    public function webhookNotification(Request $request)
    {
        $signature = $request->header('Signature');

        if (!$signature) {
            return response()->json(['error' => 'Signature missing'], 400);
        }

        $body = $request->getContent();

        // Decode the Base64 signature
        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return response()->json(['error' => 'Invalid Base64 signature'], 500);
        }

        // Hash the request body
        $hashedBody = hash('sha256', $body, true);

        // Retrieve public key from API and cache it for 10 minutes
        $pubKey = Cache::remember('external_public_key', 600, function () {
            $response = Http::acceptJson()->get('https://api.brla.digital:5567/v1/pubkey');
            if ($response->failed()) {
                Log::error('Failed to fetch public key', ['response' => $response->body()]);
                return null;
            }
            return $response->json('publicKey');
        });

        if (!$pubKey) {
            return response()->json(['error' => 'Unable to retrieve public key'], 500);
        }

        // Convert public key to OpenSSL format
        $formattedKey = "-----BEGIN PUBLIC KEY-----\n" . 
                        trim(str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n"], '', $pubKey)) . 
                        "\n-----END PUBLIC KEY-----\n";

        $keyResource = openssl_pkey_get_public($formattedKey);
        if (!$keyResource) {
            return response()->json(['error' => 'Invalid public key format'], 500);
        }

        // Verify ECDSA signature
        $verified = openssl_verify($hashedBody, $decodedSignature, $keyResource, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Signature validated
        Log::info('Valid webhook received', ['body' => $body]);

        // check if it's deposit or virtual account
        if (ctype_digit($body['referenceLabel'])) {
            // complete regular deposit
            $this->processBrlaDeposit($body);
        } elseif (ctype_alnum($body['referenceLabel'])) {
            $virtual_account = VirtualAccount::where("account_id", $body['referenceLabel'])->first(); 
            if($virtual_account) {
                // it's a virtual account deposit
                $this->processVirtualAccountDeposit($body);
            }
        }

        return response()->json(['message' => 'Webhook received successfully'], 200);
    }

    public function processBrlaDeposit()
    {
        $record = Deposit::where()->first();
        if (!isset($record['referenceLabel'], $record['status'])) {
            // Log::error("Skipping record due to missing keys", ['record' => json_encode($record)]);
            continue;
        }

        $transactionStatus = strtolower($record['status']);

        $txn = TransactionRecord::where('transaction_id', $deposit->id)->where('transaction_memo', 'payin')->first();
        if (!$txn) {
            // Log::error("Transaction record not found", ['transaction_id' => $deposit->id]);
            continue;
        }

        try {
            if ($transactionStatus === 'paid') {
                $txn->update(['transaction_status' => 'In Progress']);
                // Log::info("Processing deposit completion", ['status' => $transactionStatus]);
                $depositService = new DepositService();
                // Log::info('Deposit service class instatiated');
                // Log::info("TRansaction ID is: ", ['txn_id' => $txn->id]);
                $depositService->process_deposit($txn->transaction_id);
                // Log::info("Processing deposit completed", ['status' => $transactionStatus]);
            } else {
                // Log::info("Updating deposit status", ['status' => $transactionStatus]);
                $txn->update(["transaction_status" => $transactionStatus]);
                $deposit->update(['status' => $transactionStatus]);
            }
        } catch (\Throwable $th) {
            Log::error("Error while completing Brla payin: ", ['msg' => $th->getMessage()]);
        }
    }

    public function processVirtualAccountDeposit($payload)
    {
        
        $amount = (float) $payload['amount'];
        $currency = strtoupper($payload['currency']);

        $acc = VirtualAccount::where('account_number', $payload['details']['receive_clabe'])->first();
        if(!$acc) {
            die(200);
        }


        $user = User::whereId($acc->user_id)->first();

        BitsoWebhookLog::create([
            'fid' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'method' => $payload['method'],
            'method_name' => $payload['method_name'],
            'amount' => $amount,
            'details' => ($payload['details'] ?? []),
        ]);

         // record deposit info into the DB
        $deposit = new Deposit();
        $deposit->currency = $payload['currency'];
        $deposit->deposit_currency = $payload['currency'] ?? "BRL";
        $deposit->user_id = $user->id;
        $deposit->amount = $payload['amount'];
        $deposit->gateway = 0;
        $deposit->status = "complete";
        $deposit->receive_amount = floatval($payload['amount']);
        $deposit->meta = [
            'transaction_id' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'amount' => $payload['amount'],
            'method' => $payload['method_name'],
            'network' => $payload['network'],
            'sender_name' => $payload['details']['sender_name'] ?? null,
            'sender_clabe' => $payload['details']['sender_clabe'] ?? null,
            'receiver_clabe' => $payload['details']['receive_clabe'] ?? null,
            'sender_bank' => $payload['details']['sender_bank'] ?? null,
            'concept' => $payload['details']['concepto'] ?? null,
        ];
        $deposit->save();
    
        VirtualAccountDeposit::updateOrCreate([
            "deposit_id" => $deposit->id,
            "currency" => $deposit->currency,
            "amount" => $deposit->amount,
            "account_number" => $payload['details']['receive_clabe'],
            "status" => "complete",
        ]);
    
        TransactionRecord::create([
            "user_id" => $user->id,
            "transaction_beneficiary_id" => $user->id,
            "transaction_id" => $payload['fid'],
            "transaction_amount" => $payload['amount'],
            "gateway_id" => null,
            "transaction_status" => "completed",
            "transaction_type" => 'virtual_account',
            "transaction_memo" => "payin",
            "transaction_currency" => $payload['currency'] ?? "BRL",
            "base_currency" => $payload['currency'] ?? "BRL",
            "secondary_currency" => $payload['currency'] ?? "BRL",
            "transaction_purpose" => "VIRTUAL_ACCOUNT_DEPOSIT",
            "transaction_payin_details" => ['payin_data' => $payload],
            "transaction_payout_details" => null,
        ]);
        
        $wallet = $user->getWallet('brl');
        if($wallet) {
            $wallet->deposit(floatval($payload['amount'] * 100));
        }
    }
}
