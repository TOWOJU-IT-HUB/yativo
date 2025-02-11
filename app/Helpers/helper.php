<?php

use App\Http\Controllers\MiscController;
use App\Models\Balance;
use App\Models\Business;
use App\Models\BusinessConfig;
use App\Models\Country;
use App\Models\CustomPricing;
use App\Models\Deposit;
use App\Models\ExchangeRate;
use App\Models\Gateways;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use App\Models\settings;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\WhitelistedIP;
use App\Services\EncryptionService;
use Creatydev\Plans\Models\PlanModel;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Currencies\app\Models\Currency;
use Modules\Customer\app\Models\Customer;
use Modules\SendMoney\app\Models\SendMoney;
use App\Models\WalletTransaction;
use Bavix\Wallet\Exceptions\BalanceIsEmpty;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Support\Facades\Cache;
use Modules\SendMoney\app\Models\SendQuote;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

if (!function_exists('user_can')) {
    /**
     * @return bool
     */
    function user_can($permission)
    {
        $result = false;
        $user = request()->user() ?? [];
        if ($user && $user->isAbleTo($permission)) {
            $result = true;
        }
        return $result;
    }
}

if (!function_exists('to_array')) {
    /**
     * convert object to array
     */
    function to_array($data): array
    {
        return (array) $data;
    }
}

if (!function_exists('result')) {
    /**
     * convert object to array
     * 
     * @return array
     */
    function result($data)
    {
        if (is_array($data)) {
            return $data;
        } else if (is_object($data)) {
            return json_decode(json_encode($data), true);
        } else {
            return json_decode($data, true);
        }
    }
}


if (!function_exists('isApi')) {
    function isApi()
    {
        if (request()->is('api/*')) {
            return true;
        }
    }
}


if (!function_exists('smart_sms')) {
    function smart_sms($message, $phoneNumber)
    {
        return true;
    }
}

if (!function_exists('_date')) {
    function _date($date)
    {
        return $date->format('M. d, Y');
    }
}

if (!function_exists('convertIntToDecimal')) {
    function convertIntToDecimal($integerValue, $precision = 2)
    {
        $decimalValue = number_format($integerValue, $precision, '.', '');
        return $decimalValue;
    }
}

if (!function_exists('settings')) {
    /**
     * Gera a paginação dos itens de um array ou collection.
     *
     * @param array $items
     * @param int   $perPage
     * @param int  $page
     * @param array $options
     *
     * @return string
     */
    function settings(string $key, $default = null): string
    {
        $setting = Settings::where('meta_key', $key)->first();
        if (!empty($setting)) {
            $setting = $setting->meta_value;
        } else {
            return $default;
        }

        return $setting;
    }
}

if (!function_exists('get_current_balance')) {
    function get_current_balance($currency)
    {
        $where = [
            'currency' => $currency,
            'user_id' => active_user()
        ];
        $current_balance = Balance::where($where)->latest()->first();

        return $current_balance ? $current_balance->balance : 0;
    }
}

if (!function_exists('get_transaction_rate')) {
    function get_transaction_rate($send_currency, $receive_currency, $gateway, $type)
    {
        Log::info(json_encode([$send_currency, $receive_currency, $gateway, $type]));
        $result = 0;

        // Fetch exchange rate details based on gateway and type
        $rates = ExchangeRate::where('gateway_id', $gateway)
            ->where('rate_type', $type)
            ->first();

        // Fetch base rate from external service or function
        $baseRate = exchange_rates(strtoupper($send_currency), strtoupper($receive_currency));

        if ($rates) {
            if ($baseRate > 0) {
                // Calculate floated amount if float percentage is set
                $rate_floated_amount = ($rates->float_percentage ?? 0) / 100 * $baseRate;
                $result = $baseRate + $rate_floated_amount;
            } else {
                Log::error("Base rate is 0 for {$send_currency} to {$receive_currency}");
            }
        } else {
            if ($baseRate > 0) {
                $result = $baseRate;
            } else {
                Log::error("No exchange rate found for gateway ID: {$gateway}, type: {$type}");
            }
        }

        return floatval($result);
    }
}

if (!function_exists('exchange_rates')) {
    function exchange_rates($from, $to)
    {
        // If the currencies are the same, return 1
        if ($from === $to) {
            return 1;
        }

        // Initialize price to 0
        $price = 0;

        $from = explode('.', $from)[0];
        $to = explode('.', $to)[0];

        try {
            // Use Guzzle to make a reliable API request
            $client = new Client();
            $response = $client->get("https://min-api.cryptocompare.com/data/price", [
                'query' => [
                    'fsym' => $from,
                    'tsyms' => $to
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            // Extract the price from the response
            if (isset($data[$to])) {
                $price = $data[$to];
            } else {
                Log::error("Invalid response for {$from} to {$to}: " . json_encode($data));
            }
        } catch (\Exception $e) {
            // Log any exception that occurs during the API request
            Log::error("Failed to fetch exchange rate for {$from} to {$to}: " . $e->getMessage());
        }

        return $price;
    }
}

if (!function_exists('getExchangeVal')) {
    /**
     * Get and return the exchange rate
     */
    function getExchangeVal($currency1, $currency2)
    {
        return exchange_rates($currency1, $currency2);
    }
}

if (!function_exists('per_page')) {
    /**
     * Get and return the exchange rate
     */
    function per_page($perPage = null)
    {
        if (isset(request()->per_page)) {
            $perPage = request()->per_page;
        }
        return $perPage ?? 10;
    }
}

if (!function_exists('removeEmptyArrays')) {
    /**
     * Get current user object
     */
    function removeEmptyArrays($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = removeEmptyArrays($value); // Recursively call the function for nested arrays
                if (empty($value)) {
                    unset($array[$key]); // Remove empty arrays
                }
            } elseif ($value === null || $value === '') {
                unset($array[$key]); // Remove null or empty values
            }
        }
        return $array;
    }
}

if (!function_exists('gateways')) {
    /**
     * @param string $slug
     * @return boolean
     */
    function gateways(string $slug)
    {
        $gateway = Gateways::where('slug', $slug)->first();
        if ($gateway) {
            return $gateway->status;
        }
        return false;
    }
}

if (!function_exists('getGatewayById')) {
    /**
     * @param string $slug
     * @return object
     */
    function getGatewayById(int $id)
    {
        $gateway = payoutMethods::where('id', $id)->first();
        return $gateway;
    }
}

if (!function_exists('getCurrencyById')) {
    /**
     * @param string $slug
     * @return object 
     */
    function getCurrencyById(int $id)
    {
        $currency = Currency::where('id', $id)->first();
        return $currency;
    }
}

if (!function_exists('user')) {
    function user()
    {
        if (!auth()->check()) {
            return null;
        }

        return auth()->user();
    }
}

if (!function_exists('get_business_id')) {
    function get_business_id($userId)
    {
        $bis = Business::whereUserId($userId)->latest()->first();
        return $bis->id;
    }
}

if (!function_exists('get_success_response')) {
    function get_success_response($data, $status_code = 200, $message = "Request successful")
    {
        if (isset($data['error'])) {
            return get_error_response($data);
        }

        $response = [
            'status' => 'success',
            'status_code' => $status_code,
            'message' => $message,
            'data' => $data
        ];
        // return $response;
        return response()->json($response);
    }
}

if (!function_exists('get_error_response')) {
    function get_error_response($data, $status_code = 400, $message = "Request failed.")
    {
        if (isset($data['error'])) {
            if (isset($data['error']['error'])) {
                $data = $data['error']['error'];
            } else {
                $data = $data['error'];
            }
        }

        if (is_string($data)) {
            $data = ["error" => $data];
        }
        $response = [
            'status' => 'failed',
            'status_code' => $status_code,
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response, $status_code);
    }
}

if (!function_exists('uuid')) {
    /**
     * @return string uniquid()
     * return string uuid()
     */
    function uuid($length = 8, $prefix = '')
    {
        return strtoupper($prefix . Str::random($length));
    }
}

if (!function_exists('getCustomerById')) {
    /**
     * @return string uniquid()
     * @return array|object
     */
    function getCustomerById($customer_id)
    {
        return Customer::whereCustomerId($customer_id)->first();
    }
}

if (!function_exists('generate_uuid')) {
    /**
     * @return string uniquid()
     * return string generate_uuid()
     */
    function generate_uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('save_image')) {
    function save_image($path, $image)
    {
        $result = [];
        if (!empty($image) and is_file($image)) {
            $user = request()->user();
            if (is_file($image)) {
                $imgPath = $image->store("documents/$user->membership_id", 'r2');
                $result['document'] = getenv("CLOUDFLARE_BASE_URL") . $imgPath;
            }

            return getenv("CLOUDFLARE_BASE_URL") . $imgPath;
        }
        return $result;
    }
}


if (!function_exists('save_base64_image')) {
    function save_base64_image($path, $imagePath)
    {
        $result = null;

        if (!empty($imagePath) && file_exists($imagePath)) {
            // Assuming user is authenticated; modify if needed
            $user = request()->user();
            $userPath = "documents/{$user->membership_id}";

            // Upload the file to the Cloudflare R2 storage
            $imgPath = "{$userPath}/" . basename($imagePath);
            Storage::disk('r2')->put($imgPath, file_get_contents($imagePath));

            // Generate and return the full Cloudflare URL
            $result = getenv("CLOUDFLARE_BASE_URL") . $imgPath;
        }

        return $result;
    }
}


if (!function_exists('get_fees')) {
    /**
     * @param string crypto #Ex: BUSD
     * @param string|float|int amount
     * @param string fiat #Ex:  USD
     */
    function get_fees($coin, $amount, $fiat)
    {
        //convert amount to crypto and calculate the fee
        try {
            return 1;
            // return [
            //     'cryptoAmount'  =>  $amount,
            //     'feeInCrypto'   =>  0,
            // ];
            // $fee = 0;
            // $gas_fee = settings('gas_fee');
            // $calculateCryptoRate = app('bitpowr');
            // $calculateCryptoRate = $calculateCryptoRate->marketPrice($fiat);
            // $cryptoAmount = $calculateCryptoRate[$coin] * $amount;
            // if (!empty($gas_fee)) {
            //     $fee = (($gas_fee->value / 100) * $cryptoAmount);
            // }
            // // $feeInCrypto = $cryptoAmount;
            // return [
            //     'cryptoAmount' => $cryptoAmount,
            //     'feeInCrypto' => $fee,
            // ];
        } catch (\Throwable $th) {
            echo get_error_response($th->getMessage(), 500);
            exit;
        }
    }
}

if (!function_exists('slugify')) {
    /**
     * Gera a paginação dos itens de um array ou collection.
     *
     * @param array $items
     * @param int   $perPage
     * @param int  $page
     * @param array $options
     *
     */
    function slugify(string $title): string
    {
        return Str::slug($title) . Str::random(4);
    }
}

if (!function_exists('get_commision')) {
    /* 
     * @param array $options
     *
     */
    function get_commision($amount, $percentage)
    {
        $commission = (($amount / 100) * $percentage);
        return $commission;
    }
}

if (!function_exists('active_user')) {
    function active_user()
    {
        if (auth()->check()) {
            return auth()->id();
        }
        return false;
    }
}

if (!function_exists('get_user')) {
    function get_user($userId)
    {
        $user = User::find($userId);
        return $user;
    }
}

if (!function_exists('monnet_error_code')) {
    /**
     * Monnet payin error codes
     */
    function monnet_error_code($code)
    {
        $errorMessages = [
            "0001" => "Error in payinMerchantID not valid (the field is empty)",
            "0002" => "Error in payinAmount not valid (the field is empty)",
            "0003" => "Error in payinCurrency not valid (the field is empty)",
            "0004" => "Error in payinMerchantOperationNumber not valid (the field is empty)",
            "0005" => "Error in payinVerification not valid (the field is empty)",
            "0006" => "Error in payinTransactionErrorURL not valid (the field is empty)",
            "0007" => "Error in payinTransactionOKURL not valid (the field is empty)",
            "0008" => "Error in payinProcessorCode not valid",
            "0009" => "Error payinMerchantID not valid (it's wrong)",
            "0010" => "Error in payinVerification (it's wrong)",
            "0011" => "Error in merchant not enabled",
            "0012" => "Error in payinTransactionErrorURL not valid",
            "0013" => "Error in payinTransactionOKURL not valid",
            "0015" => "Error in payinAmount format not valid",
            "0017" => "Error in payinCurrency not valid",
            "0018" => "Error in processor not valid",
            "0019" => "Error in currency, not exist for merchant",
            "0022" => "Error in transaction payinCustomerTypeDocument no exits",
            "0023" => "Error in transaction, payinCustomerDocument no exits",
            "0024" => "Error in transaction, payinCustomerDocument no exits",
            "0025" => "Customer Type Document invalid",
            "0026" => "Customer Document invalid",
            "0030" => "Error due to non-compliance with pre-authorization rules (only for Argentina)",
            "0031" => "Error in processor, code value no registered",
            "0032" => "Error in processor, key no registered",
            "0040" => "Error in transaction, cbu is required",
            "0041" => "Error in transaction, cuit is requiredYUNO",
            "0042" => "Error on sendGateWay YUNO",
            "0099" => "Internal Error Payin",
        ];

        return $errorMessages[$code] ?? "Error code not found";
    }
}

if (!function_exists('sendOtpEmail')) {
    function sendOtpEmail($email, $otp)
    {
        // Customize the email content as needed
        $subject = 'Verification OTP';
        $message = "Your OTP verification code is: $otp";

        // Send the email
        Mail::raw($message, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }
}

if (!function_exists('get_iso2')) {
    /**
     * @return country 2 codes identifier
     */
    function get_iso2($country)
    {
        $country = Country::whereUuid($country);
        return $country->iso2;
    }
}

if (!function_exists('get_iso3_by_iso2')) {
    /**
     * @return country 2 codes identifier
     */
    function get_iso3_by_iso2($country)
    {
        $country = Country::where('iso2', $country);
        return $country->iso3;
    }
}

if (!function_exists('updateSendMoneyRawData')) {
    /**
     * @return void
     */
    function updateSendMoneyRawData($quoteId, $data): void
    {
        SendMoney::whereid($quoteId)->update(
            [
                'raw_data' => $data
            ]
        );
    }
}

if (!function_exists('updateDepositRawData')) {
    /**
     * @return void
     */
    function updateDepositRawData($depositId, $data): void
    {
        Deposit::whereid($depositId)->update(
            [
                'raw_data' => $data
            ]
        );
    }
}


if (!function_exists('get_quote_by_id')) {
    /**
     * Get send money quote by id
     * @param mixed $quoteId
     * @return array
     */
    function get_quote_by_id($quoteId = null)
    {
        $quote = [];
        if (null != $quoteId) {
            $quote = SendQuote::whereId($quoteId)->first();
            if ($quote) {
                $quote = $quote->toArray();
            }
        }
        return $quote;
    }
}

if (!function_exists('get_deposit_by_id')) {
    /**
     * Get send money quote by id
     * @param mixed $quoteId
     * @return array
     */
    function get_deposit_by_id($quoteId = null)
    {
        $quote = [];
        if (null != $quoteId) {
            $quote = Deposit::whereId($quoteId)->first();
            if ($quote) {
                $quote = $quote->toArray();
            }
        }
        return $quote;
    }
}


if (!function_exists("get_whitelisted_ips")) {
    /**
     * Retreive all IP Address whitelisted by customer
     * 
     * @param int $userId
     * 
     * return object|array|Model
     */
    function get_whitelisted_ips($userId)
    {
        $ip_lists = WhitelistedIP::whereUserId($userId)->get()->toArray();
        return $ip_lists;
    }
}

if (!function_exists("get_currency_by_id")) {
    /**
     * Retreive all IP Address whitelisted by customer
     * 
     * @param int $currencyId
     * 
     * return object|array|Model
     */
    function get_currency_by_id($currencyId)
    {
        $currency = Currency::whereId($currencyId)->first();
        return $currency;
    }
}

if (!function_exists("generateSignature")) {
    /**
     * Generates an HMAC SHA-256 signature for an HTTP request.
     *
     * @param string $apiSecret Your API secret key.
     * @param int $nonce A unique, increasing integer.
     * @param string $httpMethod The HTTP method (e.g., 'GET', 'POST').
     * @param string $requestPath The path of the request.
     * @param string $jsonPayload The JSON payload as a string.
     * @return string The generated HMAC signature.
     */

    function generateSignature($nonce, $path, $RequestPath, $HTTPMethod, $JSONPayload)
    {

        $apiSecret = env("BITSO_SECRET_KEY", "RWhSMVZWuh");
        $apiKey = env("BITSO_API_KEY", "1129e0c1d14dc4b3e9ef2de4a8c08f23");
        $message = $nonce . $HTTPMethod . $RequestPath . $JSONPayload;
        $signature = hash_hmac('sha256', $message, $apiSecret);
        $format = 'Bitso %s:%s:%s';
        $authHeader = sprintf($format, $apiKey, $nonce, $signature);
        $result = url_request($path, $HTTPMethod, $JSONPayload, $authHeader);

        return ($result);
    }
}

if (!function_exists("url_request")) {
    #function to perform curl url request depending on type and method
    function url_request($path, $HTTPMethod, $JSONPayload, $authHeader = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $HTTPMethod);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $JSONPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}


if (!function_exists('paginate_yativo')) {
    function paginate_yativo($paginator, $status_code = 200)
    {
        return [
            'status' => 'success',
            'status_code' => $status_code,
            'message' => 'Records retrieved successfully',
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ]
        ];
    }
}


if (!function_exists('add_transaction_details')) {
    /** 
     * @param int    user_id
     * @param int    transaction_beneficiary_id
     * @param int    transaction_id
     * @param float  transaction_amount
     * @param int    gateway_id
     * @param string transaction_status
     * @param string - Debit or Credit transaction_type
     * @param string optional transaction_memo
     * @param string optional transaction_purpose
     * @param array  optional transaction_payin_details
     * @param array  optional transaction_payout_details*/
    function add_transaction_detials(...$params)
    {
        $save = TransactionRecord::create($params);
        return $save;
    }
}

if (!function_exists('add_usd_virtual_card_deposit')) {
    function add_usd_virtual_card_deposit($data)
    {
        // TransactionRecord::create([
        //     "user_id" => $data['user_id'],
        //     "transaction_beneficiary_id" => $data['user_id'],
        //     "transaction_id" => $data['id'],
        //     "transaction_amount" => $data['user_id'],
        //     "gateway_id" => 0,
        //     "transaction_status" => "completed",
        //     "transaction_type" => $txn_type,
        //     "transaction_memo" => "payin",
        //     "transaction_currency" => $currency,
        //     "base_currency" => $currency,
        //     "secondary_currency" => $paymentMethods->currency,
        //     "transaction_purpose" => request()->transaction_purpose ?? "Deposit",
        //     "transaction_payin_details" => array_merge([$send, $result]),
        //     "transaction_payout_details" => [],
        // ]);
    }
}



if (!function_exists('decryptCustomerData')) {
    /**
     * Encrypt customer data with AES-256-GCM using the app key
     *
     * @param mixed $customerData
     * @return string Base64-encoded string of IV, ciphertext, and tag
     */
    function encryptCustomerData($customerData)
    {
        // Get the app key from environment variables and ensure it's exactly 32 bytes
        $appKey = substr(env('APP_KEY'), 0, 32);

        // Generate a random IV for each encryption
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));

        // Encrypt the data
        $encryptedData = openssl_encrypt(
            json_encode($customerData),
            'aes-256-gcm',
            $appKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        // Concatenate the IV, encrypted data, and tag
        $finalData = base64_encode($iv . $encryptedData . $tag);

        return $finalData;
    }
}

if (!function_exists('decryptCustomerData')) {
    /**
     * Decrypt customer data with AES-256-GCM using the app key
     *
     * @param string $encryptedData Base64-encoded string of IV, ciphertext, and tag
     * @return array|null The decrypted customer data as an associative array, or null if decryption fails
     */
    function decryptCustomerData($encryptedData)
    {
        // Get the app key from environment variables and ensure it's exactly 32 bytes
        $appKey = substr(env('APP_KEY'), 0, 32);

        // Decode the base64-encoded data
        $decodedData = base64_decode($encryptedData);

        // Extract the IV, encrypted data, and tag
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        $iv = substr($decodedData, 0, $ivLength);
        $tag = substr($decodedData, -16);
        $ciphertext = substr($decodedData, $ivLength, -16);

        // Decrypt the data
        $decryptedData = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $appKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        // Return the decoded data or null if decryption failed
        return $decryptedData === false ? null : json_decode($decryptedData, true);
    }
}




if (!function_exists('convertToUSD')) {
    function convertToUSD($currency, $amount)
    {
        if (strtoupper($currency) == "USD" || strtoupper($currency) == "USDT" || strtoupper($currency) == "USDC") {
            return $amount;
        }
        // Check if the exchange rate is already cached
        $cacheKey = 'exchange_rate_' . $currency . '_USD';
        $exchangeRate = Cache::remember($cacheKey, 30 * 60, function () use ($currency) {
            try {
                $client = new Client();
                $response = $client->get('https://min-api.cryptocompare.com/data/price', [
                    'query' => [
                        'fsym' => $currency,
                        'tsyms' => 'USD',
                    ]
                ]);
                $data = json_decode($response->getBody(), true);

                if (isset($data['USD'])) {
                    return $data['USD'];
                }
            } catch (\Exception $e) {
                // Log the exception or handle it accordingly
                error_log(json_encode(['message' => $e->getMessage(), 'trace' => $e->getTrace()]));
            }
            return null;
        });

        if ($exchangeRate !== null) {
            return $amount * $exchangeRate;
        }

        // If currency conversion fails, you can try another FX API here or return null
        return null;
    }
}

if (!function_exists('debit_user_wallet')) {
    function debit_user_wallet($amount, $currency = "USD", $description = 'Charge for service')
    {
        if (!$currency or empty($currency)) {
            // return ['error' => 'Invalid currency provided'];
            $currency = "USD";
        }

        $request = request();
        $user = $request->user();
        // Find or create wallet for the user
        $wallet = $user->getWallet($currency);

        try {
            if (!$wallet) { // wallet not found
                $usdWallet = $user->getWallet('USD');



                // Convert the amount to USD
                $convertedAmount = convertToUSD($currency, $amount);
                // var_dump($convertedAmount); exit;

                if ($convertedAmount === null || $convertedAmount <= 0) {
                    return ['error' => 'Currency conversion failed or insufficient balance: '];
                }

                if ($usdWallet && $usdWallet->balance >= $convertedAmount) {
                    // Charge the USD wallet
                    $usdWallet->withdraw($convertedAmount, [
                        'description' => $description,
                    ]);

                    $currency = 'USD'; // Log the transaction in USD
                    $amount = $convertedAmount; // Update amount to converted USD value
                    $wallet = $usdWallet; // Update wallet to USD wallet for further processing
                } else {
                    return ['error' => 'Insufficient balance in USD wallet'];
                }
            }

            // Try to charge the wallet
            $charge = $wallet->withdraw($amount, [
                'description' => $description,
            ]);

            if (!$charge) {
                return ['error' => "Sorry we're currently unable to charge your balance."];
            }

            // Log transaction
            WalletTransaction::create([
                'user_id' => $user->id,
                'currency' => $currency,
                'amount' => -$amount, // negative amount for deduction
                'description' => $description,
            ]);

            return ['success' => true, 'message' => 'Transaction completed successfully'];
        } catch (InsufficientFunds $exception) {
            // User doesn't have enough balance in wallet
            return ['error' => $exception->getMessage()];
        } catch (BalanceIsEmpty $e) {
            // User doesn't have enough balance in any wallet
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions
            return ['error' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('encryptShuftiProToken')) {
    /**
     * @param string shiftiPro - YOUR-CLIENT-ID:YOUR-SECRET-KEY 
     * 
    //  * @return string
     */
    function encryptShuftiProToken(string $plaintext)
    {
        $cipher = "AES-256-CBC";
        $key = "1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f708192a3b4c5d6e7f809"; // 32 bytes
        $iv = "1a2b3c4d5e6f708192a3b4c5d6e7f809"; // 16 bytes
        $encrypted = openssl_encrypt($plaintext, $cipher, $key, 0, $iv);
        if ($encrypted === false) {
            // Handle error
            $error = openssl_error_string();
            // Log or throw an exception with the error message
        }
        return [
            "encrypt" => base64_encode(ddencrypt($encrypted, $key, $iv)),
            // "decrypt" => base64_encode(dddecrypt($encrypted, $key, $iv)),
        ];
    }
}

function ddencrypt($plaintext, $key, $iv)
{
    $cipher = "AES-256-CBC";
    $encrypted = openssl_encrypt($plaintext, $cipher, hex2bin($key), 0, hex2bin($iv));
    return base64_encode($encrypted);
}

function dddecrypt($ciphertext, $key, $iv)
{
    $cipher = "AES-256-CBC";
    $decrypted = openssl_decrypt(base64_decode($ciphertext), $cipher, hex2bin($key), 0, hex2bin($iv));
    return $decrypted;
}

if (!function_exists('get_transaction_fee')) {
    /**
     * Summary of get_transaction_fee
     * @param mixed $gateway - payin ID | payout ID
     * @param mixed $amount | 100
     * @param mixed $txn_type deposit | payout
     * @param mixed $gateway_type - payin | payout
     * @return int|array
     */
    function get_transaction_fee(int $gateway, float $amount, string $txn_type, string $gateway_type)
    {
        $fee = $total_charge = 0;
        $rate = 0;
        $rate_floated_amount = 0;

        // get user pricing model
        $user = auth()->user();
        if (!$user->hasActiveSubscription()) {
            $plan = PlanModel::where('price', 0)->latest()->first();
            if ($plan) {
                $user->subscribeTo($plan, 30, true);
            }
        }

        $subscription = $user->activeSubscription();
        $user_plan = (int) $subscription->plan_id;

        // Handle gateway logic
        if (strtolower($gateway_type) == "payin"):
            $gateway = PayinMethods::whereId($gateway)->first();
        elseif (strtolower($gateway_type) == "payout"):
            $gateway = payoutMethods::whereId($gateway)->first();
        else:
            return ['error' => "Invalid gateway selected"];
        endif;

        if ($user_plan === 3) {
            // Handle custom pricing (Plan 3)
            $customPricing = CustomPricing::where('user_id', $user->id)
                ->where('gateway_id', $gateway->id)
                ->first();

            if (!$customPricing) {
                // If no custom pricing is found, fall back to Plan 2 pricing
                $user_plan = 2;
            } else {
                $fixed_charge = $customPricing->fixed_charge;
                $float_charge = $customPricing->float_charge;
            }
        }

        if ($user_plan === 1 || $user_plan === 2) {
            // Handle basic and pro plans
            $fixed_charge = $user_plan === 1 ? $gateway->fixed_charge : $gateway->pro_fixed_charge;
            $float_charge = $user_plan === 1 ? $gateway->float_charge : $gateway->pro_float_charge;
        }

        // Calculate the fee
        $exchange_rate = getExchangeVal("USD", strtoupper($gateway->currency));
        $fee = $xchange_rate * $fixed_charge;
        $rate = $float_charge;
        $rate_floated_amount = ($amount * $rate) / 100;

        $total_charge = $fee + $rate_floated_amount;
        $minimum_charge = $gateway->minimum_charge;
        $maximum_charge = $gateway->maximum_charge;

        // Compare total charge with minimum and maximum charges
        if ($total_charge < $minimum_charge) {
            $total_charge = $minimum_charge;
        } elseif ($total_charge > $maximum_charge) {
            $total_charge = $maximum_charge;
        }

        return $total_charge;
    }
}

if (!function_exists('formatSettlementTime')) {
    function formatSettlementTime($settlementTime)
    {
        // Calculate total days, remaining hours, and minutes
        $days = floor($settlementTime / 24);
        $hours = floor($settlementTime % 24);
        $minutes = round(($settlementTime - floor($settlementTime)) * 60);

        // Format the output based on days, hours, and minutes
        $output = [];
        if ($days > 0) {
            $output[] = $days . " Day(s)";
        }
        if ($hours > 0) {
            $output[] = $hours . " Hour(s)";
        }
        if ($minutes > 0) {
            $output[] = $minutes . " Minute(s)";
        }

        if (!empty($output)) {
            return implode(" ", $output);
        } else {
            return "0 Minute(s)"; // In case the settlement time is 0
        }
    }
}


if (!function_exists('convertToBase64ImageUrl')) {
    function convertToBase64ImageUrl($base64String)
    {
        // Decode the base64 string
        $decodedString = base64_decode($base64String);

        // Check if decoding was successful
        if ($decodedString === false) {
            return null; // Return null or handle the error as needed
        }

        // Convert the decoded string back to base64 for image embedding
        $imageBase64 = base64_encode($decodedString);

        // Construct the data URI for the image
        return MiscController::uploadBase64ImageToCloudflare('data:image/png;base64,' . $imageBase64);
    }
}

if (!function_exists('get_payout_code')) {
    function get_payout_code($currency)
    {
        // Path to the JSON file
        $jsonPath = public_path('payment-methods/local-payments/payout-code.json');

        // Check if the file exists
        if (File::exists($jsonPath)) {
            // Get the content of the JSON file
            $jsonContent = File::get($jsonPath);

            // Decode the JSON into an array
            $payoutData = json_decode($jsonContent, true);

            // Loop through the array to find the matching ISO3
            foreach ($payoutData as $payout) {
                if (isset($payout['currency']) && $payout['currency'] === strtoupper($currency)) {
                    return $payout;
                }
            }
        }

        // Return null if no match is found or file does not exist
        return null;
    }
}

if (!function_exists('update_deposit_gateway_id')) {
    /**
     * update the ID of checkout gateway into the deposit DB for easy record retrieval
     * @param mixed $depositId
     * @param mixed $gatewayDepositId
     * @return void
     */
    function update_deposit_gateway_id($depositId, $gatewayDepositId)
    {
        $deposit = Deposit::find($depositId);
        if ($deposit) {
            $deposit->gateway_deposit_id = $gatewayDepositId;
            $deposit->save();
        }
    }
}

if (!function_exists('generateTableFromArray')) {
    function generateTableFromArray($data)
    {
        // Start the table structure
        $html = '<table class="table table-bordered table-striped w-full mt-4">';

        // Iterate over the array to create table rows
        foreach ($data as $key => $value) {
            // Check if the value is an array itself (nested data)
            if (is_array($value)) {
                // If so, recursively call the function to handle the nested array
                $html .= '<tr><th colspan="2">' . ucfirst(str_replace('_', ' ', $key)) . '</th></tr>';
                $html .= '<tr><td colspan="2">' . generateTableFromArray($value) . '</td></tr>';
            } else {
                // If it's a simple key-value pair, display it in a row
                $html .= '<tr>';
                $html .= '<td><strong>' . ucfirst(str_replace('_', ' ', $key)) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
                $html .= '</tr>';
            }
        }

        // Close the table
        $html .= '</table>';

        return $html;
    }
}


if (!function_exists('config_can_peform')) {
    /**
     * update the ID of checkout gateway into the deposit DB for easy record retrieval
     * @param mixed $depositId
     * @param mixed $gatewayDepositId
     * @return boolean
     */
    function config_can_peform($action): bool
    {
        $config = BusinessConfig::where('user_id', auth()->id())->pluck('configs');
        if ($config && isset($config[0][$action]) && strtolower($config[0][$action]) === "enabled") {
            return true;
        }

        return true;
    }
}
