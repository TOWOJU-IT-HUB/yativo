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
use DB;
use Illuminate\Support\Facades\Http;

class CronDepositController extends Controller
{
    public function brla()
    {
        $ids = $this->getGatewayPayinMethods('brla');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $brla = new BrlaDigitalService();
    
        // Log::info("All Pending brla payin are: ", ['payins' => $deposits]);
    
        foreach ($deposits as $deposit) {
            $curl = $brla->getPayInHistory(['referenceLabel' => $deposit->gateway_deposit_id]);
    
            // Debug API response
            // Log::info("Raw API Response", ['response' => json_encode($curl)]);
    
            if (!is_array($curl) || empty($curl['depositsLogs'])) {
                // Log::warning("Brla Payin Response is empty or invalid", ['response' => json_encode($curl)]);
                continue;
            }
    
            // Log::info("Processing depositsLogs", ['count' => count($curl['depositsLogs'])]);
    
            foreach ($curl['depositsLogs'] as $record) {
                // Log::info("I am here", ['record' => json_encode($record)]);
    
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
        }
    }

    public function vitawallet()
    {
        $ids = $this->getGatewayPayinMethods('vitawallet');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $vitawallet = new VitaWalletController();

        foreach ($deposits as $deposit) {
            $response = $vitawallet->getTransaction($deposit->gateway_deposit_id);

            // Ensure response is properly decoded as an array
            if (is_object($response)) {
                $response = json_decode(json_encode($response), true);
            }

            if (!is_array($response) || !isset($response['transactions'][0]['attributes'])) {
                \Log::warning("Invalid VitaWallet response", ['deposit_id' => $deposit->id, 'response' => $response]);
                continue;
            }

            $payload = $response['transactions'][0]['attributes'];

            // Ensure 'order' exists in the payload
            if (!isset($payload['order'])) {
                \Log::warning("VitaWallet transaction missing 'order'", ['deposit_id' => $deposit->id, 'payload' => $payload]);
                continue;
            }

            if (isset($payload['status']) && ($payload['status'] === true || $payload['status'] === "completed")) {
                $order = TransactionRecord::where([
                    "transaction_memo" => "payin",
                    "transaction_id" => $deposit->id
                ])->first();

                if ($order) {
                    $deposit_services = new DepositService();
                    $deposit_services->process_deposit($order->transaction_id);
                }
            }
        }
    }
    
    public function transfi()
    {
        $ids = $this->getGatewayPayinMethods('transfi');
        $deposits = Deposit::whereIn('gateway', $ids)->whereStatus('pending')->get();
        $transfi = new TransFiController();

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
                    // Log::info("deposit info for floid is: ", ['deposit' => $deposit]);
                    $order = $this->getfloid(strtolower($deposit->deposit_currency), $deposit->gateway_deposit_id);
    
                    // Log the full API response
                    // Log::info("Floid API Response", [
                    //     'gateway_deposit_id' => $deposit->gateway_deposit_id,
                    //     'response' => $order
                    // ]);

                    if(is_array($order) && isset($order[0])) {
                        $order = $order[0];
                    }
    
                    // Check if response has a valid status
                    if (!isset($order['status'])) {
                        // Log::error("Floid API Response Missing Status", [
                        //     'gateway_deposit_id' => $deposit->gateway_deposit_id,
                        //     'response' => $order
                        // ]);
                        continue;
                    }
    
                    $txn = TransactionRecord::where('transaction_id', $deposit->id)->first();
                    if (!$txn) {
                        // Log::error("Transaction record not found for deposit", ['deposit_id' => $deposit->id]);
                        continue;
                    }
    
                    $transactionStatus = strtolower($order['status']);
    
                    // Log transaction status
                    // Log::info("Processing Floid Deposit", [
                    //     'deposit_id' => $deposit->id,
                    //     'transaction_status' => $transactionStatus
                    // ]);
    
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
                    // Log::error("Error processing Floid deposit", [
                    //     'deposit_id' => $deposit->id,
                    //     'error' => $e->getMessage()
                    // ]);
                }
            }
        } catch (\Exception $e) {
            // Log::error("Error in getFloidStatus cron job", ['error' => $e->getMessage()]);
        }
    }
    
    private function getfloid(string $currency, string $id)
    {
        try {
            // Determine currency code
            $cur = $currency === "clp" ? "cl" : "pe";
            $authToken = env("FLOID_AUTH_TOKEN");
            $payload = ['payment_token' => $id];
            $url = "https://api.floid.app/{$cur}/payments/check/";
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer '.$authToken,
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $result = $response;
            if(!is_array($result)) {
                return json_decode($result, true);
            }
            // Log::info([
            //     "url" => $url,
            //     'payload' => $payload,
            //     'token' => $authToken,
            //     'currency' => $cur,
            //     'response' => $response
            // ]);
            
            // Log::info("Response from Floid for {$id} status: ", $result);
            return $result;
        } catch (\Exception $e) {
            // Log::error("Error calling Floid API", [
            //     'gateway_deposit_id' => $id,
            //     'error' => $e->getMessage()
            // ]);
            return null;
        }
    }

}
