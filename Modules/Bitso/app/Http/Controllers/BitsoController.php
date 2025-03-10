<?php

namespace Modules\Bitso\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BitsoAccounts;
use App\Models\BitsoWebhookLog;
use App\Models\Deposit;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
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

    public function withdraw($amount, $beneficiaryId, $currency)
    {
        $clabe = null;
        // Get beneficiary info
        $beneficiary = BeneficiaryPaymentMethod::whereId($beneficiaryId)->first();
        if (!$beneficiary) {
            return ['error' => 'Beneficiary not found'];
        }

        if (isset($beneficiary->payment_data)) {
            $clabe = $beneficiary->payment_data->clabe;
        }

        if (null == $clabe || empty($clabe)) {
            return ['error' => 'Error retreieveing clabe number'];
        }

        $result = $this->bitso->payout($amount, $clabe, $currency);
        return $result;
    }

    public function deposit_webhook(Request $request)
    {
        try {
            $input = $request->getContent();
            $webhookData = json_decode($input, true);
    
            Log::info("Incoming Bitso webhook data", ['incoming' => $webhookData]);

            if (!isset($webhookData['event'], $webhookData['payload'])) {
                Log::error("Bitso: Invalid webhook payload received.", ['payload' => $input]);
                return response()->json(['error' => 'Invalid webhook payload'], 400);
            }
    
            // Check if the event is 'funding' and the status is 'complete'
            if (strtolower($webhookData['event']) === 'funding' && isset($payload['details']['receive_clabe'])){
                $complete_action = $this->handleClabeDeposit($webhookData);
            }

            // Check if the event is 'funding' and the status is 'complete'
            if ($webhookData['event'] === 'funding'){
                $complete_action = $this->handleClabeDeposit($webhookData);
            }

            // Check if the event is 'funding' and the status is 'complete'
            if ($webhookData['event'] === 'funding'){
                $complete_action = $this->handleClabeDeposit($webhookData);
            }


            return response()->json(['success' => 'Deposit processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Bitso: Error processing deposit webhook.", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    private function handleClabeDeposit($payload)
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

    protected static function handleFunding(array $payload): void
    {
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
        BitsoWebhookLog::create([
            'fid' => $payload['wid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'method' => $payload['method'],
            'amount' => $payload['amount'],
            'details' => ($payload['details'] ?? []),
        ]);

        Withdrawal::create([
            'transaction_id' => $payload['wid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'amount' => $payload['amount'],
            'method' => $payload['method'],
            'destination' => $payload['details']['destination'] ?? null,
        ]);
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
