<?php

namespace App\Http\Controllers;

use App\Models\Business\VirtualAccount;
use App\Models\Deposit;
use App\Models\LocalPaymentAccounts;
use App\Models\localPaymentTransactions;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Notifications\VirtualAccountDepositNotification;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\SendMoney\app\Jobs\CompleteSendMoneyJob;
use Modules\SendMoney\app\Models\SendMoney;

class LocalPaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $incoming = file_get_contents('php://input');
        Log::info('Incoming from local Payments', ['data' => $incoming]);
        Log::info(json_encode(['LocalPayment incoming Logs' => $request->data]));
        $payload = $request->data;

        if (empty($payload)) {
            $payload = json_decode($incoming, true);
        }

        if (!isset($payload['data'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload structure'], 400);
        }

        $payload = $payload['data'];

        // Get external and internal IDs
        $externalId = $payload['externalId'] ?? null;
        $internalId = $payload['internalId'] ?? null;

        // Detect transaction type
        $transactionType = $payload['transactionType'] ?? null;

        if (!$externalId || !$internalId || !$transactionType) {
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        if ($this->isTransactionProcessed($externalId, $internalId)) {
            return response()->json(['status' => 'already processed'], 200);
        }

        switch ($transactionType) {
            case 'PayIn':
                $this->processPayIn($payload);
                break;

            case 'PayOut':
                $this->processPayOut($payload);
                break;

            case 'VirtualAccount':
                $this->processVirtualAccount($payload);
                break;

            default:
                return response()->json(['status' => 'error', 'message' => 'Unknown transaction type'], 400);
        }

        return response()->json(['status' => 'success']);
    }

    protected function processPayIn($payload)
    {
        Log::info("Processing payin:", ['info' => $payload]);
        $amount = $payload['data']['amount'] ?? $payload['amount'] ?? 0;
        $quoteId = $payload['externalId'] ?? $payload['data']['externalId'] ?? null;
        // Save transaction as processed
        $isVirtualAccountDeposit = false;
        // then notification is a payin to virtual account
        if (isset($payload['beneficiary']['bank']['account']['number'])) {
            $isVirtualAccountDeposit = true;
            $this->saveTransaction($payload, $amount, $isVirtualAccountDeposit);
            return $this->processVirtualAccountDeposit($payload, $payload['beneficiary']['bank']['account']['number']);
        } else {
            $this->saveTransaction($payload, $amount, $isVirtualAccountDeposit);

            // Process the PayIn transaction
            $order = TransactionRecord::where("transaction_id", $quoteId)->latest()->first();


            switch ($order->transaction_type) {
                case "deposit":
                    Log::channel("deposit_log")->info("Processing Local payment webhook", $order->toArray());
                    $this->processDeposit($order->id, 'deposit');
                    break;
                default:
                    $send_money = SendMoney::where('quote_id', $quoteId)->where('status', 'pending')->first();
                    CompleteSendMoneyJob::dispatchAfterResponse($quoteId);
                    break;
            }
        }
        http_response_code(200);
    }

    protected function processPayOut($payload)
    {
        $amount = $payload['data']['amount'] ?? $payload['amount'] ?? 0;

        // Save transaction as processed
        $this->saveTransaction($payload, $amount);

        // Process the PayOut transaction
        Log::info("Processed PayOut transaction with amount: $amount");
    }

    protected function processVirtualAccount($payload)
    {
        // Save transaction as processed
        $this->saveTransaction($payload);

        // Process the VirtualAccount transaction
        Log::info("Processed VirtualAccount transaction");
    }

    protected function processVirtualAccountDeposit($payload, $accountNumber)
    {
        Log::info("Processing VirtualAccount Deposit", ['account_number' => $accountNumber]);
        // retrieve account details owner
        $account = VirtualAccount::where('account_number', $accountNumber)->first();
        if ($account) {
            $user = User::findOrFail($account->user_id);
            if ($user && $user->hasWallet($account->currency)) {
                // retrieve and credit deposit
                $wallet = $user->getWallet($account->currency);
                $wallet->deposit($payload['amount'] * 100, ['txId' => $payload['externalId'], 'details' => $payload['beneficiary']]); // 646010319801292000
                $user->notify(new VirtualAccountDepositNotification($payload));
            }
        } else {
            Log::channel('deposit_log')->info("Virtual Account Deposit", ['info' => $payload, 'account_number' => $accountNumber]);
        }
        // Process the VirtualAccount transaction
        Log::info("Processed VirtualAccount transaction");
    }

    protected function isTransactionProcessed($externalId, $internalId)
    {
        return localPaymentTransactions::where('external_id', $externalId)
            ->orWhere('internal_id', $internalId)
            ->exists();
    }

    protected function saveTransaction($payload, $amount = 0, $isVirtualAccountDeposit = false)
    {
        localPaymentTransactions::create([
            'external_id' => $payload['externalId'] ?? $payload['data']['externalId'],
            'internal_id' => $payload['internalId'] ?? $payload['data']['internalId'],
            'account_number' => $payload['beneficiary']['bank']['account']['number'] ?? 'non_virtual_account_payin',
            'transaction_type' => $isVirtualAccountDeposit ? "virtual_account_payin" : ($payload['transactionType'] ?? $payload['data']['transactionType'] ?? null),
            'amount' => $amount,
            'provider_response' => $payload
        ]);
    }

    private function processDeposit($quoteId, $productName)
    {
        Log::notice("Localpayment Webhook for Deposit for: " . $quoteId);
        $order = TransactionRecord::whereId($quoteId)
            ->where('transaction_type', $productName)
            ->first();

        if ($order) {
            $deposit_services = new DepositService();
            $deposit_services->process_deposit($order->id);
        } else {
            Log::error("Order with the Provided ID not found!. ID: {$quoteId}");
        }
    }

}
