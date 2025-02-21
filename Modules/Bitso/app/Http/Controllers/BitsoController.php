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
                $account->customer_id = $customerId;
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
    
            if (!isset($webhookData['event'], $webhookData['payload'])) {
                Log::error("Bitso: Invalid webhook payload received.", ['payload' => $input]);
                return response()->json(['error' => 'Invalid webhook payload'], 400);
            }
    
            $payload = $webhookData['payload'];
    
            // Check if the event is 'funding' and the status is 'complete'
            if ($webhookData['event'] !== 'funding' || $payload['status'] !== 'complete') {
                return response()->json(['error' => 'Invalid webhook event or status'], 400);
            }
    
            // Prevent duplicate processing
            if (BitsoWebhookLog::where('fid', $payload['fid'])->exists()) {
                return response()->json(['error' => 'Deposit already processed'], 200);
            }
    
            // Find deposit record
            $deposit = Deposit::where('txn_id', $payload['fid'])->first();
            if (!$deposit) {
                Log::error("Bitso: Deposit record not found.", ['fid' => $payload['fid']]);
                return response()->json(['error' => 'Deposit record not found'], 404);
            }
    
            // Find user
            $user = User::find($deposit->user_id);
            if (!$user) {
                Log::error("Bitso: User not found.", ['user_id' => $deposit->user_id]);
                return response()->json(['error' => 'User not found'], 404);
            }
    
            // Get deposit amount & currency
            $amount = $payload['amount'];
            $currency = strtolower($payload['currency']);
    
            // Update deposit status
            $deposit->update([
                'status' => 'completed',
                'raw_data' => json_encode($webhookData),
            ]);
    
            // Find user's wallet
            $wallet = $user->getWallet($currency);
            if (!$wallet) {
                Log::error("Bitso: Wallet not found for user.", ['user_id' => $user->id, 'currency' => $currency]);
                return response()->json(['error' => 'Wallet not found'], 404);
            }
    
            // Deposit funds into wallet
            $wallet->deposit($amount * 100, ['description' => 'Wallet deposit top-up']);
    
            // Log the webhook
            BitsoWebhookLog::create([
                'fid' => $payload['fid'],
                'status' => $payload['status'],
                'currency' => $payload['currency'],
                'method' => $payload['method'],
                'method_name' => $payload['method_name'],
                'amount' => $amount,
                'details' => json_encode($payload['details'] ?? []), // Ensure JSON storage
            ]);
    
            // Create a transaction record
            TransactionRecord::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'type' => 'deposit',
                'status' => 'completed',
                'reference' => $payload['fid'],
                'description' => 'Deposit via ' . $payload['method_name'],
            ]);
    
            // Create tracking record
            Track::create([
                'quote_id' => $payload['fid'],
                'tracking_status' => 'Deposit completed successfully',
                'raw_data' => json_encode($webhookData),
            ]);
    
            // Notify user
            $user->notifyNow(new WalletNotification($amount, "Deposit", $currency));
    
            return response()->json(['success' => 'Deposit processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Bitso: Error processing deposit webhook.", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function getDepositStatus($fid)
    {
        //
    }
}
