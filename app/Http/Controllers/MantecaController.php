<?php

namespace App\Http\Controllers;

use Log;
use App\Models\Track;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;

class MantecaController extends Controller
{
    protected $baseUrl = 'https://sandbox.manteca.dev/crypto/v1/';
    protected $headers;

    public function __construct()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
            'md-api-key' => env('MANTECA_API_KEY'),
        ];

        $this->baseUrl =  env('MANTECA_BASE_URL', 'https://sandbox.manteca.dev/crypto/v1/');

        if (!Schema::hasColumn('customers', 'manteca_user_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('manteca_user_id')->nullable();
            });
        }
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'legalId' => 'required',
            'country' => 'required',
            'state' => 'required',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $payload = [
            "name" => $customer->customer_name,
            "email" => $customer->customer_email,
            "legalId" => $request->legalId,
            "phoneNumber" => $customer->customer_phone,
            "country" => $request->country,
            "civilState" => $request->state,
            "externalId" => generate_uuid(),
            "isPep" => false,
            "isFatca" => false,
            "isUif" => false
        ];

        $response = Http::withHeaders($this->headers)->post($this->baseUrl . 'user', $payload);
        $result = $response->json();

        if (isset($result["numberId"])) {
            $customer->update([
                "manteca_user_id" => $result["numberId"]
            ]);

            return get_success_response(['message' => "Customer enrolled successfully"]);
        }
        Log::debug("Error creating Manteca user: ", ['response' => $response]);
        return get_error_response(['error' => "Unable to enroll customer"]);
    }

    public function getUploadUrl($userId, $docType = 'DNI_BACK', $fileName = 'passport.jpg')
    {
        $payload = [
            'docType' => $docType,
            'fileName' => $fileName
        ];

        $finalUrl = "{$this->baseUrl}documentation/{$userId}/uploadUrl";
        
        $query = Http::withHeaders($this->headers)
            ->post($finalUrl, $payload);

        $response = $query->json();

        if (isset($response['url']) && null !== $response['url']) {
            return $response['url'] ?? null;
        }

        Log::debug("Error creating Manteca user: ", ['response' => $response]);
        return ['error' => "Failed to get upload URL. Error: " . $response];
    }

    public function uploadToS3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'docType' => 'required|string|in:DNI_FRONT,DNI_BACK,FUNDS,BALANCE,ESTATUTO,IIBB,ACTA_DESIGNACION,ULTIMO_BALANCE_CERTIFICADO,CONSTANCIA_DE_CUIT,EXTRA,PEP_DDJJ,SUJETO_OBLIGADO_DDJJ,SELFIE,CERTIFICACION_CONTABLE,IMPUESTO_CEDULAR,PRIVATE_REPORT',
            'document' => 'required|file|mimes:jpg,jpeg,png',
            'fileName' => 'required',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();
        $document = $request->file('document');
        $filename = $request->fileName;
        $mimeType = $document->getMimeType();

        try {
            // upload document front
            $uploadUrl = $this->getUploadUrl($customer->manteca_user_id, $request->docType, $request->fileName);
            if (!$uploadUrl) {
                return get_error_response(['error' => 'Upload URL not received'], 500);
            }

            Log::info("Upload url is: ", ['url' => $uploadUrl]);
            
            
            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => $uploadUrl,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'PUT',
              CURLOPT_POSTFIELDS => array($request->fileName => $request->document),
              CURLOPT_HTTPHEADER => []
            ));
            
            $response = curl_exec($curl);
            
            curl_close($curl);
            if($response) {
                $response = (array)$response;
            }


            return get_success_response(['message' => 'File uploaded successfully', 'response' => $response]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()], 500);
        }
    }

    public function getPriceRate($payload)
    {
        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'order/lock', $payload);

        Log::debug("Error generating price rate: ", ['response' => $response->json()]);
        return $response->json();
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,customer_id',
            'amount' => 'required|numeric|min:1',
            'coin' => 'required|string|in:USD,ARS,PUSD,GTQ,CRC',
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        // CHECK IF THE COIN IS IN THE SUPPORTED PAIR AND GRAB THE CRYPTO
        $asset = $this->mapFiatToPair($request->coin);
        if(!$asset || null == $asset) {
            return get_error_response(['error' => 'Please contact support']);
        }

        // record as deposit request
        $txnId = generate_uuid();
        
        $payload = [
            "externalId" => $txnId,
            "userAnyId" => "100007696", //$customer->manteca_user_id,
            // "userNumberId" => $customer->manteca_user_id,
            "sessionId" => generate_uuid(),
            "asset" => $asset['crypto'],
            "against" => $asset['fiat'],
            "assetAmount" => $request->amount,
            "withdrawAddress" => "0x742d35Cc6634C0532925a3b844Bc454e4438f44e",
            "withdrawNetwork" => "BASE"
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'synthetics/ramp-on', $payload);

        $result = $response->json();

        if(!isset($result['id'])) {
            return get_error_response($result);
            // return get_error_response(['error' => 'Unable to initiate deposit, please contact support']);
        }

        // Record deposit
        $deposit = new Deposit();
        $deposit->currency = $asset[0];
        $deposit->deposit_currency = $asset[1];
        $deposit->user_id = active_user();
        $deposit->amount = $request->amount;
        $deposit->gateway = $request->gateway;
        $deposit->receive_amount = $request->amount;
        $deposit->save();

        TransactionRecord::create([
            "user_id" => auth()->id(),
            "transaction_beneficiary_id" => active_user(),
            "transaction_id" => $txnId,
            "transaction_amount" => $request->amount,
            "gateway_id" => 999999,
            "transaction_status" => "In Progress",
            "transaction_type" => 'epay',
            "transaction_memo" => "payin",
            "transaction_currency" => $request->coin,
            "base_currency" => $request->coin,
            "secondary_currency" => $request->coin,
            "transaction_purpose" => request()->transaction_purpose ?? "Deposit",
            "transaction_payin_details" => array_merge([$payload, $response]),
            "transaction_payout_details" => [],
        ]);

        Track::create([
            "quote_id" => $txnId,
            "transaction_type" => $txn_type ?? 'deposit',
            "tracking_status" => "Deposit initiated successfully",
            "raw_data" => (array) $response
        ]);

        if (isset($result['externalId'])) {
            // Log::debug("Manteca deposit details: ", ['payload' => $payload, 'response' => $response->json()]);
            $finalResponse = [
                'id' => $txnId,
                // 'bank_account' => $result['details']['depositAddress'] ?? null,
                'deposit_alias' => $result['details']['depositAlias'] ?? null,
                'price_expire_at' => $result['details']['priceExpireAt'] ?? null,
                // 'receiving_currency' => $asset[1],

                'cvu' => $result['details']['depositAddress'] ?? null,
                'order_expiration_time' => $result['stages']['1']['expireAt'] ?? null,
                'amount_to_be_paid' => $result['stages']['1']['thresholdAmount'] ?? null,
                'deposit_currency' => $result['stages']['1']['asset'] ?? null,
                'expected_receiving_amount' => $result['stages']['2']['assetAmount'] ?? null,
                'rate_expire_at' => $result['details']['priceExpireAt'] ?? null,
            ];
            return get_success_response($finalResponse);
        }


        Log::debug("Error creating Manteca deposit: ", ['payload' => $payload, 'response' => $response]);
        return get_error_response(['error' => 'Unable to process order']);
    }

    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,customer_id',
            'coin' => 'required|string|in:ARS,USD,EUR',
            'cbu' => 'required|string',
            'amount' => 'required|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }

        $customer = Customer::where('customer_id', $request->customer_id)->first();

        $payload = [
            'userId' => $customer->manteca_user_id,
            'coin' => $request->coin,
            'cbu' => $request->cbu,
            'amount' => $request->amount
        ];

        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . 'fiat/withdraw', $payload);

        if ($response->ok()) {
            return get_success_response($response->json()[0]);
        }

        Log::debug("Error creating Manteca payout: ", ['response' => $response]);
        return get_error_response(['error' => 'Unable to process withdrawal']);
    }

    private function mapFiatToPair(string $against): ?array {
        $map_list = [
            'USDC_ARS',
            'USDT_USD',
            'USDCB_GTQ',
            'USDCB_CRC',
            'USDCB_PUSD'
        ];
        
        foreach ($map_list as $item) {
            [$crypto, $fiat] = explode('_', $item);
            if ($fiat === $against) {
                return [
                    'crypto' => $crypto,
                    'fiat' => $fiat
                ];
            }
        }
    
        // Return null if no match found
        return null;
    }
    
}
