<?php

namespace Modules\Bitso\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BitsoAccounts;
use App\Models\BitsoWebhookLog;
use App\Models\Deposit;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Withdraw;
use App\Models\UserMeta;
use App\Notifications\WalletNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Bitso\app\Services\BitsoServices;

class BitsoController extends Controller
{
    public $bitso;

    public function __construct()
    {
        $this->bitso = new BitsoServices();
    }

    /**
     * @return array
     * @method post
     * GET sample response : {"success":true,"payload":{"total_items":"1","total_pages":"1","current_page":"1","page_size":"25","response":[{"clabe":"710969000035509567","type":"INTERNATIONAL","status":"ENABLED","created_at":"2024-04-08T19:06:19","updated_at":null}]}}
     */
    public function deposit($depositId, $amount, $currency)
    {
        $user = auth()->user();
        $bitso = new BitsoServices();
        $payload = '';
        $result = [];
        $request = request();

        if($request->has('customer_id')) {
            $customer = Customer::where('customer_id', $request->customer_id)->first();
        } 

        $phone = $customer->customer_phone ?? $user->phone;
        $email = $customer->customer_email ?? $user->email;
        $fullname = $customer->customer_name ?? $user->name ?? $user->business->business_legal_name;
        $bank_code = $request->bank_code;
        $documentType = $request->documentType;
        $documentNumber = $request->documentNumber;

        if (strtolower($currency) == 'mxn') {
        } elseif (strtolower($currency) == 'cop') {
            $result = $bitso->depositCop($amount, $phone, $email, $documentType, $documentNumber, $fullname, $bank_code);
            if (!is_array($result)) {
                $result = json_decode($result->fid, true);
            }

            update_deposit_gateway_id($depositId, $result['data']['id']);
            Log::info(json_encode(['response_cop' => $result]));
            // return $result;
            if (isset($result['success']) && $result['success'] == true) {
                $payload = $result['payload'];
                $account = new BitsoAccounts();
                $account->customer_id = $customer->customer_id ?? null;
                $account->user_id = $user->id;
                $account->account_number = $payload['clabe'];
                $account->provider_response = $payload;
                $account->save();
            } else {
                return [
                    "error" => "Error retreieveing clabe number, please contact support"
                ];
            }
        }

        return $result;
    }

    /**
     * @param float $amount
     * @param string $clabe => beneficiary account clabe number
     * 
     * sample response: {
     *  "success": true,
     *  "payload": {
     *      "wid": "33a6dccd89eea62f3d4a2c5ac1623c2a",
     *      "status": "pending",
     *      "created_at": "2024-04-24T18:20:17+00:00",
     *      "currency": "mxn",
     *      "method": "praxis",
     *      "method_name": "SPEI Transfer",
     *      "amount": "5.00",
     *      "asset": "mxn",
     *      "network": "spei",
     *      "protocol": "clabe",
     *      "integration": "praxis",
     *      "details": {
     *          "fecha_operacion": null,
     *          "beneficiary_bank_code": null,
     *          "huella_digital": null,
     *          "concepto": "nd",
     *          "beneficiary_name": "Zee Technologies SPA",
     *          "beneficiary_clabe": "012180015105083524",
     *          "numeric_ref": "1",
     *          "cep_link": null
     *      }
     *  }
     *  }
     * 
     * @return array
     */

    public function withdraw($amount, $beneficiaryId, $currency, $payoutId)
     {
        $clabe = null;
        // Get beneficiary info
        $model = new BeneficiaryPaymentMethod();
        $ben = $model->getBeneficiaryPaymentMethod($beneficiaryId);
        $payload = Withdraw::whereId($payoutId)->first();
        $amount = floor($payload->customer_receive_amount);
         if (!$ben) {
             session()->flash('error', 'Beneficiary not found');
             return ['error' => 'Beneficiary not found'];
         }
     
         $pay_data = $ben->payment_data;
     
        if (strtolower($currency) == 'mxn') {
            if (isset($ben->payment_data)) {
                $clabe = $ben->payment_data['clabe'] ?? null;
            }
    
            if (empty($clabe)) {
                session()->flash('error', 'Error retrieving clabe number');
                return ['error' => 'Error retrieving clabe number'];
             }
     
            $data = [
                "method" => "praxis",
                "amount" => $amount,
                "currency" => "mxn",
                "beneficiary" => $pay_data['beneficiary'] ?? "N/A",
                "clabe" => $clabe,
                "protocol" => "clabe",
                "origin_id" => $payoutId
            ];
        } elseif (strtolower($currency) == 'cop') {
            // ✅ Trim and validate document_id to be between 6 and 10 digits
            $document_id = preg_replace('/\D/', '', trim($pay_data['document_id'])); // Remove non-numeric characters
            if (strlen($document_id) < 6 || strlen($document_id) > 10) {
                return ['error' => 'Invalid document_id format. Must be 6-10 digits.'];
            }
    
        $data = [
            'currency' => 'cop',
            'protocol' => 'ach_co',
            'amount' => $amount,
            'bankAccount' => $pay_data['bankAccount'],
            'bankCode' => $pay_data['bankCode'],
            'AccountType' => (int) $pay_data['AccountType'],
            'beneficiary_name' => $pay_data['beneficiary_name'],
            'beneficiary_lastname' => $pay_data['beneficiary_lastname'],
            'document_id' => $document_id,
            'document_type' => strtoupper($pay_data['document_type']),
            'email' => "noreply@yativo.com", 
            "third_party_withdrawal" => true,
            "origin_id" => $payoutId
        ];
        } else {
            return ['error' => "We currently cannot process this currency"];
        }

        $result = $this->bitso->payout($data);
        if(is_array($result) && isset($result['success']) && $result['success'] == false) {
            $result = ['error' => $result['error']['message']];
        }

        if(isset($result['success']) && $result['success'] == true) {
            mark_payout_completed($payload->id, $payload->payout_id);
        }

        var_dump($result); exit;
        return $result;
     }
     

    public function deposit_webhook(Request $request)
    {
        try {
            $input = $request->getContent();
            $webhookData = json_decode($input, true);
    
            $payload = $webhookData['payload'];
            Log::info("Incoming Bitso webhook data", ['incoming' => $webhookData]);

            if (!isset($webhookData['event'], $webhookData['payload'])) {
                Log::error("Bitso: Invalid webhook payload received.", ['payload' => $input]);
                return response()->json(['error' => 'Invalid webhook payload'], 400);
            }

            // Check if the event is 'funding' and the status is 'complete'
            if ($webhookData['event'] === 'funding' && isset($payload['asset']) && $payload['asset'] == "usdt"){
                return self::processCryptoDeposit($payload);
            }
    
            // Check if the event is 'funding' and the status is 'complete'
            if (strtolower($webhookData['event']) === 'funding' && isset($payload['details']['receive_clabe'])){
                $complete_action = $this->handleClabeDeposit($payload);
            }
            
            // Check if the event is 'funding' and the status is 'complete'
            if ($webhookData['event'] === 'funding'){
                $complete_action = $this->handleClabeDeposit($payload);
            }

            // Check if the event is 'funding' and the status is 'complete'
            if ($webhookData['event'] === 'withdrawal'){
                $complete_action = $this->handleWithdrawal($webhookData['payload']);
            }


            return response()->json(['success' => 'Deposit processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Bitso: Error processing deposit webhook.", ['error' => $e->getMessage(), 'track' => $e->getTrace()]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    private function handleClabeDeposit(array $payload)
    {
        Log::debug("debug bitso crypto depost: handleClabeDeposit: -", ['payload' => $payload]);
        try {
            if(isset($payload['currency']) && $payload['currency'] == 'usdt' && $payload["status"] == "complete") {
                self::processCryptoDeposit($payload);
            }
        } catch (\Exception $e) {
            Log::error("processCryptoDeposit failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        

        $amount = (float) $payload['amount'];
        $currency = strtoupper($payload['currency']);

        $acc = VirtualAccount::where('account_number', $payload['details']['receive_clabe'])->first();
        if(!$acc) {
           return false;
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
        $deposit->deposit_currency = $payload['currency'];
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
            "transaction_currency" => $payload['currency'] ?? "MXN",
            "base_currency" => $payload['currency'] ?? "MXN",
            "secondary_currency" => $payload['currency'] ?? "MXN",
            "transaction_purpose" => "VIRTUAL ACCOUNT DEPOSIT",
            "transaction_payin_details" => ['payin_data' => $payload],
            "transaction_payout_details" => null,
        ]);
        
        $wallet = $user->getWallet('mxn');
        if($wallet) {
            $wallet->deposit(floatval($payload['amount'] * 100));
        }
    }

    public static function handleWebhook(array $webhookData): void
    {
        $webhookData = isset($webhookData[0]) ? $webhookData : [$webhookData];

        foreach ($webhookData as $event) {
            if (self::isDuplicate($event['payload'])) {
                Log::info('Duplicate webhook received', ['event' => $event]);
                continue;
            }

            self::logWebhook($event);

            switch ($event['event']) {
                case 'funding':
                    self::handleFunding($event['payload']);
                    break;
                case 'withdrawal':
                    self::handleWithdrawal($event['payload']);
                    break;
                case 'trade':
                    self::handleTrade($event['payload']);
                    break;
                default:
                    Log::warning('Unknown Bitso event type', ['event' => $event]);
                    break;
            }
        }
    }

    public static function processCryptoDeposit($payload)
    {
        Log::debug("processing crypto payin");
        $amount = (float) $payload['amount'];
        $currency = strtoupper($payload['asset'] ?? $payload['currency']);
        $exists = BitsoWebhookLog::where('fid', $payload['fid'])->exists();
        if($exists) {
            Log::debug("Transaction exists");
            exit;
        }

        BitsoWebhookLog::create([
            'fid' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $currency,
            'method' => $payload['method'],
            'method_name' => $payload['method_name'],
            'amount' => $amount,
            'details' => json_encode($payload['details'] ?? []),
        ]);

        if($payload['details']['receiving_address'] == "0xB86f958060D265AC87E34D872C725F86A169f830"){
            // credit onramp USD 
            $onramp = User::whereEmail()->first();
            if($onramp) {
                $onramp->getWallet('usd')->deposit($payload['amount'] * 100);
                Log::debug("completed crypto payin");
            }
        }
    
        Deposit::create([
            'transaction_id' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $currency,
            'amount' => $amount,
            'method' => $payload['method_name'],
            'network' => $payload['network'],
            'sender_name' => null, // No sender name in payload
            'sender_clabe' => null, // Not present in payload
            'receiver_clabe' => $payload['details']['receiving_address'] ?? null,
            'sender_bank' => null, // Not present in payload
            'concept' => null, // Not present in payload
        ]);
    
        TransactionRecord::create([
            'user_id' => User::where('email', $payload['details']['receiving_address'] ?? '')->first()->id ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'type' => 'deposit',
            'status' => 'completed',
            'reference' => $payload['fid'],
            'description' => 'Deposit via ' . $payload['method_name'],
        ]);
    
        Track::create([
            'quote_id' => $payload['fid'],
            'tracking_status' => 'Deposit completed successfully',
            'raw_data' => json_encode($payload),
        ]);
        http_response_code(200);
        exit;
    }

    protected static function handleFunding(array $payload)
    {
        Log::debug("debug bitso crypto depost", ['payload' => $payload]);
        if(isset($payload['asset']) && $payload['asset'] == 'usdt' && $payload["status"] == "complete") {
            return self::processCryptoDeposit($payload);
        }

        $amount = (float) $payload['amount'];
        $currency = strtoupper($payload['currency']);

        BitsoWebhookLog::create([
            'fid' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'method' => $payload['method'],
            'method_name' => $payload['method_name'],
            'amount' => $amount,
            'details' => ($payload['details'] ?? []),
        ]);

        Deposit::create([
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
        ]);

        TransactionRecord::create([
            'user_id' => User::where('email', $payload['details']['sender_name'] ?? '')->first()->id ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'type' => 'deposit',
            'status' => 'completed',
            'reference' => $payload['fid'],
            'description' => 'Deposit via ' . $payload['method_name'],
        ]);

        Track::create([
            'quote_id' => $payload['fid'],
            'tracking_status' => 'Deposit completed successfully',
            'raw_data' => ($payload),
        ]);
    }

    protected static function handleWithdrawal(array $payload): void
    {
        if(strtolower($payload['status']) === "pending") {
            die("Status is still pending");
        }

        try {
            // Log the webhook payload
            BitsoWebhookLog::create([
                'fid' => $payload['wid'],
                'status' => $payload['status'],
                'currency' => $payload['currency'],
                'method_name' => $payload['method'],
                'amount' => $payload['amount'],
                'details' => ($payload['details'] ?? []),
            ]);
        } catch (\Exception $e) {
            Log::error("Error logging webhook payload: " . $e->getMessage());
        }
    
        try {
            if (!isset($payload['details']['origin_id'])) {
                Log::error("Transaction ID not found in payload.");
                return; // Exit gracefully if no transaction ID found
            }
    
            $txn_id = $payload['details']['origin_id'];
            $payout = Withdraw::whereId($txn_id)->first();
    
            if ($payout) {
                try {
                    $payout->status = strtolower($payload['status']);
                    $payout->save();
    
                    // Update transaction record also
                    $txn = TransactionRecord::where(['transaction_id' => $txn_id, 'transaction_memo' => 'payout'])->first();
                    if ($txn) {
                        $txn->transaction_status = $payout->status;
                        $txn->save();
                    }
    
                    // Save both payout and transaction records
                    if ($payout->save() && $txn->save()) {
                        // If transaction is failed, refund customer
                        if (strtolower($payout->status) === "failed") {
                            $user = User::whereId($payout->user_id)->first();
                            if ($user) {
                                try {
                                    $wallet = $user->getWallet($payout->debit_wallet);
                                    $wallet->deposit($payout->debit_amount, [
                                        "description" => "refund",
                                        "full_desc" => "Refund for payout {$payout->id}",
                                        "payload" => $payout
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error("Error processing refund for payout {$payout->id}: " . $e->getMessage());
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing payout transaction for {$txn_id}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("Error handling withdrawal for {$txn_id}: " . $e->getMessage());
        }
    }
    

    protected static function handleTrade(array $payload): void
    {
        BitsoWebhookLog::create([
            'fid' => $payload['tid'],
            'status' => $payload['status'],
            'currency' => $payload['pair'],
            'amount' => $payload['amount'],
            'details' => ($payload ?? []),
        ]);

        Trade::create([
            'trade_id' => $payload['tid'],
            'status' => $payload['status'],
            'pair' => $payload['pair'],
            'side' => $payload['side'],
            'amount' => $payload['amount'],
            'price' => $payload['price'],
        ]);
    }

    protected static function logWebhook(array $event): void
    {
        BitsoWebhookLog::create([
            'event' => $event['event'],
            'payload' => ($event['payload']),
            'received_at' => now(),
        ]);
    }

    protected static function isDuplicate(array $payload): bool
    {
        return BitsoWebhookLog::where('fid', $payload['fid'] ?? $payload['wid'] ?? null)->exists();
    }



    public function getDepositStatus($fid)
    {
        //
    }
}
