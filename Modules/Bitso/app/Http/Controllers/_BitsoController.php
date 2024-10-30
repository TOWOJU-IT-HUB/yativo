<?php

namespace Modules\Bitso\app\Http\Controllers;

use App\Http\Controllers\Controller;
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

class _BitsoController extends Controller
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
    public function deposit($amount)
    {
        // return ['amount' => $amount];
        $user = auth()->id();
        $user_meta = UserMeta::where(["user_id" => $user, "key" => "clabe_number"])->first();

        if (!is_null($user_meta)) {
            return [
                "clabe_number" => $user_meta->value,
                "note" => "Please make your transfer to the clabe number, and your account will be credited within 30minutes of payment confirmation"
            ];
        }

        $result = $this->bitso->getDepositWallet($amount);
        // var_dump($result); exit;
        if (isset($result['success']) && $result['success'] == true) {
            $rest = $result['payload'];
            if (isset($rest['response'])) {
                return $rest['response'];
            } else {
                return $rest['payload'];
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

    //  {"success":true,"payload":[{"fid":"bb93bd0bf63635bde5b364a8037f9009","status":"complete","created_at":"2024-04-19T21:28:09+00:00","currency":"mxn","method":"praxis","method_name":"SPEI Transfer","amount":"50.00","asset":"mxn","network":"spei","protocol":"clabe","integration":"praxis","details":{"sender_name":"JAIME DAVID LARRAURI CARRANZA ","sender_clabe":"012180015105083524","receive_clabe":"710969000035509567","sender_bank":40012,"clave":4780746,"clave_rastreo":"MBAN01002404190075285080","numeric_reference":"1904240","concepto":"Devolucion","cep_link":"https:\/\/www.banxico.org.mx\/cep\/go?i=90710&s=20220921&d=QHwIYvBI%2BtemJ%2F7r3bofBrJcVlBuoFhvTvc33J3O3Gd%2FdgwepVBM1NnYNktA3Ch0maZYq1Bk7ilmzYymZ%2B5vPpFMieRhKH1UPRSBQKLrCH4%3D","sender_rfc_curp":"LACJ910724000","deposit_type":"third_party"}},{"fid":"e50f9600574ea98753853c2018e590f4","status":"complete","created_at":"2024-04-19T18:47:47+00:00","currency":"sol","method":"sol","method_name":"Solana","amount":"0.06900433","asset":"sol","network":"solana","protocol":"sol","integration":"fblocks-v1","details":{"receiving_address":"8PXG2tTvE1YDxRn59T4JSbz5y6q3ioh5oVkH7rGWpdkV","tx_hash":"5fLzAdZFieSJCVSXzHD9YiaaKowNpisuSmDK9dyKa1KA5JVydg7gEV2rdexrFsnq2ALiuhqCHcKXNzNBHfKdpnVA","confirmations":"0","tx_url":"https:\/\/explorer.solana.com\/tx\/5fLzAdZFieSJCVSXzHD9YiaaKowNpisuSmDK9dyKa1KA5JVydg7gEV2rdexrFsnq2ALiuhqCHcKXNzNBHfKdpnVA"}}]}
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
