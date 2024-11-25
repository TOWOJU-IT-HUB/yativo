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
    public function deposit($amount, $currency, $customerId = null)
    {
        $user = auth()->id();
        $bitso = new BitsoServices();
        $payload = '';
        $result = [];

        if (strtolower($currency) == 'mxn') {
        } elseif (strtolower($currency) == 'cop') {
            $result = $bitso->depositCop(100, "+573156289887", "mymail@bitso.com", "NIT", "9014977087", "Jane Doe", "006", "https://api.yativo.com");
            if (!is_array($result)) {
                $result = json_decode($result, true);
            }
            Log::info(json_encode(['response_cop' => $result]));
            // return $result;
            if (isset($result['success']) && $result['success'] == true) {
                $payload = $result['payload'];
                $account = new BitsoAccounts();
                $account->customer_id = $customerId;
                $account->user_id = $user;
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

        if (null == $clabe) {
            return ['error' => 'Error retreieveing clabe number'];
        }

        $result = $this->bitso->payout($amount, $clabe, $currency);
        return $result;
    }

    public function deposit_webhook(Request $request)
    {
        $input = $request->getContent();
        $webhookData = json_decode($input, true);

        // Check if the event is 'funding' and the status is 'complete'
        if ($webhookData['event'] !== 'funding' || $webhookData['payload']['status'] !== 'complete') {
            return response()->json(['error' => 'Invalid webhook event or status'], 400);
        }

        $payload = $webhookData['payload'];

        // Ensure no duplicate processing of the deposit
        if (BitsoWebhookLog::where('fid', $payload['fid'])->exists()) {
            return response()->json(['error' => 'Deposit already processed'], 200);
        }

        // Find the corresponding deposit record
        $deposit = Deposit::where('txn_id', $payload['fid'])->first();

        if (!$deposit) {
            return response()->json(['error' => 'Deposit record not found'], 404);
        }

        // Update deposit status and store raw webhook data
        $deposit->status = 'completed';
        $deposit->raw_data = json_encode($webhookData);
        $deposit->save();

        // Find the user associated with the deposit
        $user = User::find($deposit->user_id);
        if (!$user) {
            Log::info("Bitso: User with ID: {$deposit->user_id} not found!");
            return response()->json(['error' => 'User not found'], 404);
        }

        $currency = $payload['currency'];
        $amount = $payload['amount'];

        // Find user's wallet for the specific currency
        if ($wallet = $user->getWallet($currency)) {
            // Deposit the amount into the wallet (amount * 100, assuming cents format)
            $wallet->deposit($amount * 100, ['description' => 'wallet deposit topup']);
        } else {
            Log::info("Bitso: Wallet for currency {$currency} not found for user ID: {$deposit->user_id}.");
            return response()->json(['error' => 'Wallet not found'], 404);
        }

        // Log the webhook in BitsoWebhookLog
        BitsoWebhookLog::create([
            'fid' => $payload['fid'],
            'status' => $payload['status'],
            'currency' => $payload['currency'],
            'method' => $payload['method'],
            'method_name' => $payload['method_name'],
            'amount' => $payload['amount'],
            'details' => $payload['details'],
        ]);

        // Create a transaction record
        TransactionRecord::create([
            'user_id' => $deposit->user_id,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'type' => 'deposit',
            'status' => 'completed',
            'reference' => $payload['fid'],
            'description' => 'Deposit via ' . $payload['method_name'],
        ]);

        // Create a tracking record
        Track::create([
            'quote_id' => $payload['fid'],
            'tracking_status' => 'Deposit completed successfully',
            'raw_data' => json_encode($webhookData),
        ]);

        // Notify user (optional, uncomment to enable notification)
        $user->notifyNow(new WalletNotification($amount, "Withdrawal", $currency));

        return response()->json(['success' => 'Deposit processed successfully'], 200);
    }
}
