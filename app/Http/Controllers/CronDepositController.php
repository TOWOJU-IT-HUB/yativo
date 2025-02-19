<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\PayinMethods;
use App\Models\TransactionRecord;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Modules\Flow\app\Http\Controllers\FlowController;
use App\Services\BrlaDigitalService;
use Modules\Bitso\app\Services\BitsoServices;
use App\Services\OnrampService;
use Log;
use Illuminate\Support\Facades\Http;

class CronDepositController extends Controller
{
    public function brla()
    {
        $ids = $this->getGatewayPayinMethods('brla');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $brla = new BrlaDigitalService();

        foreach ($deposits as $deposit) {
            $curl = $brla->getPayInHistory(['referenceLabel' => $deposit->gateway_deposit_id]);
            if (is_array($curl) && isset($curl['depositsLogs'])) {
                foreach ($curl['depositsLogs'] as $record) {
                    if ($record['referenceLabel'] == $deposit->gateway_deposit_id) {
                        $transactionStatus = strtolower($record['status']);

                        $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                        if (!$txn) continue;

                        if ($transactionStatus === 'paid') {
                            $depositService = new DepositService();
                            $depositService->process_deposit($txn->transaction_id);
                        } else {
                            $txn->update(["transaction_status" => $transactionStatus]);
                            $deposit->update(['status' => $transactionStatus]);
                        }
                    }
                }
            }
        }
    }

    public function vitawallet()
    {
        $ids = $this->getGatewayPayinMethods('vitawallet');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $vitawallet = new VitaWalletController();

        foreach ($deposits as $deposit) {
            $curl = $vitawallet->getTransaction($deposit->gateway_deposit_id);
            if (is_array($curl) && isset($curl['transaction'])) {
                $record = $curl['transaction'];
                if (isset($record['status'])) {
                    $transactionStatus = strtolower($record['status']);
                    
                    $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                    if (!$txn) continue;
                    
                    if ($transactionStatus === 'completed') {
                        $depositService = new DepositService();
                        $depositService->process_deposit($txn->transaction_id);
                    } else {
                        $txn->update(["transaction_status" => $transactionStatus]);
                        $deposit->update(['status' => $transactionStatus]);
                    }
                }
            }
        }
    }

    public function transfi()
    {
        $ids = $this->getGatewayPayinMethods('transfi');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $transfi = new VitaWalletController();

        foreach ($deposits as $deposit) {
            $curl = $transfi->getOrderDetails($deposit->gateway_deposit_id);
            if (is_array($curl) && isset($curl['status']) && strtolower($curl['status']) === "success") {
                $record = $curl['data'];
                if (isset($record['status'])) {
                    $transactionStatus = strtolower($record['status']);
                    
                    $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                    if (!$txn) continue;
                    
                    if ($transactionStatus === 'fund_settled' && $record['type'] == "payin") {
                        $depositService = new DepositService();
                        $depositService->process_deposit($txn->transaction_id);
                    } else {
                        $txn->update(["transaction_status" => $transactionStatus]);
                        $deposit->update(['status' => $transactionStatus]);
                    }
                }
            }
        }
    }

    public function onramp()
    {
        $ids = $this->getGatewayPayinMethods('transfi');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $transfi = new OnrampService();
    
        foreach ($deposits as $deposit) {
            $curl = $transfi->orderStatus($deposit->gateway_deposit_id, 1);
    
            if (is_array($curl) && isset($curl['status']) && (int)$curl['status'] === 1) {
                $record = $curl['data'];
                if (!isset($record['orderStatus'])) continue;
                
                $transactionStatus = (int) $record['orderStatus'];
                $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                if (!$txn) continue;
                
                switch ($transactionStatus) {
                    case -4:
                        $statusMessage = 'Amount mismatch';
                        break;
                    case -3:
                        $statusMessage = 'Bank and KYC name mismatch';
                        break;
                    case -2:
                        $statusMessage = 'Transaction abandoned';
                        break;
                    case -1:
                        $statusMessage = 'Transaction timed out';
                        break;
                    case 0:
                        $statusMessage = 'Transaction created';
                        break;
                    case 1:
                        $statusMessage = 'ReferenceId claimed';
                        break;
                    case 2:
                        $statusMessage = 'Deposit secured';
                        break;
                    case 3: case 13:
                        $statusMessage = 'Crypto purchased';
                        break;
                    case 4: case 15:
                        $statusMessage = 'Withdrawal complete';
                        break;
                    case 5: case 16:
                        $statusMessage = 'Webhook sent';
                        break;
                    case 11:
                        $statusMessage = 'Order placement initiated';
                        break;
                    case 12:
                        $statusMessage = 'Purchasing crypto';
                        break;
                    case 14:
                        $statusMessage = 'Withdrawal initiated';
                        break;
                    default:
                        $statusMessage = 'Unknown status';
                        break;
                }
    
                // If the transaction is completed or beyond (4+), process the deposit
                if ($transactionStatus >= 4) {
                    $depositService = new DepositService();
                    $depositService->process_deposit($txn->transaction_id);
                }
    
                // Update transaction status and deposit record
                $txn->update(["transaction_status" => $statusMessage]);
                $deposit->update(['status' => $statusMessage]);
            }
        }
    }
    

    public function bitso()
    {
        $ids = $this->getGatewayPayinMethods('bitso');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $bitsoService = new BitsoServices();

        foreach ($deposits as $deposit) {
            $response = $bitsoService->getDepositStatus($deposit->gateway_deposit_id);
    
            if (is_array($response) && isset($response['status'])) {
                $status = strtolower($response['status']);
                $transactionId = $response['fid'] ?? null;
                $amount = $response['amount'] ?? null;
                $currency = $response['currency'] ?? null;
    
                $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                if (!$txn) continue;
    
                switch ($status) {
                    case 'complete':
                        $depositService = new DepositService();
                        $depositService->process_deposit($txn->transaction_id);
                        break;
                    default:
                        break;
                }
    
                // Update transaction and deposit records
                $txn->update(["transaction_status" => $status]);
                $deposit->update(['status' => $status]);
            }
        }
    }

    private function getGatewayPayinMethods($gateway)
    {
        return PayinMethods::where('gateway', $gateway)->pluck('id')->toArray();
    }
    
    public function getFloidStatus()
    {
        try {
            $ids = $this->getGatewayPayinMethods('floid');
            $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
            $floid = new FlowController();
    
            foreach ($deposits as $deposit) {
                try {
                    $order = $this->getfloid($deposit->currency ?? $deposit->deposit_currency, $deposit->gateway_deposit_id);
    
                    // Log the full API response
                    Log::info("Floid API Response", [
                        'gateway_deposit_id' => $deposit->gateway_deposit_id,
                        'response' => $order
                    ]);
    
                    // Check if response has a valid status
                    if (!isset($order['status'])) {
                        Log::error("Floid API Response Missing Status", [
                            'gateway_deposit_id' => $deposit->gateway_deposit_id,
                            'response' => $order
                        ]);
                        continue;
                    }
    
                    $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                    if (!$txn) {
                        Log::error("Transaction record not found for deposit", ['deposit_id' => $deposit->id]);
                        continue;
                    }
    
                    $transactionStatus = strtolower($order['status']);
    
                    // Log transaction status
                    Log::info("Processing Floid Deposit", [
                        'deposit_id' => $deposit->id,
                        'transaction_status' => $transactionStatus
                    ]);
    
                    DB::beginTransaction();
    
                    if ($transactionStatus === "success") {
                        $depositService = new DepositService();
                        $depositService->process_deposit($txn->transaction_id);
                    } else {
                        $txn->update(["transaction_status" => $transactionStatus]);
                        $deposit->update(['status' => $transactionStatus]);
                    }
    
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error processing Floid deposit", [
                        'deposit_id' => $deposit->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in getFloidStatus cron job", ['error' => $e->getMessage()]);
        }
    }
    
    private function getfloid(string $currency, string $id)
    {
        try {
            // Determine currency code
            $cur = $currency === "clp" ? "cl" : "pe";
    
            // Prepare request payload
            $payload = ['payment_token' => $id];
    
            Log::info("Sending request to Floid API", [
                'currency' => $currency,
                'gateway_deposit_id' => $id,
                'payload' => $payload
            ]);
    
            // Make the HTTP request using Laravel's HTTP client
            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . env('FLOID_AUTH_TOKEN'),
            ])->post("https://api.floid.app/{$cur}/payments/check", $payload);
    
            if ($response->failed()) {
                Log::error("Floid API request failed", [
                    'gateway_deposit_id' => $id,
                    'response' => $response->body()
                ]);
                return null;
            }
    
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Error calling Floid API", [
                'gateway_deposit_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
}
