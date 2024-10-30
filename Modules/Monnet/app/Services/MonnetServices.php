<?php

namespace Modules\Monnet\app\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Currencies\app\Models\Currency;

class MonnetServices
{
    public $baseUrl, $payinUrl;
    public function __construct()
    {
        $this->baseUrl = 'https://cert.api.payout.monnet.io'; // payout URL
        $this->payinUrl = 'https://cert.monnetpayments.com/api-payin/v3/online-payments';
    }

    public function payout($amount, $currency, $beneficiaryId, $quoteId)
    {
        try {
            // echo json_encode([$amount, $currency, $beneficiaryId, $quoteId]); exit;
            $apiSecret = getenv('MONNET_API_SECRET');
            $HTTPmethod = 'POST';
            $resourcePath = '/api/v1/125/payouts';
            $timestamp = '?timestamp=' . time();
            $request = request();
            $description = $request->description ?? 'Payout requesst';
            // get full details of the currency
            if (is_numeric($currency)) {
                $curr = get_currency_by_id($currency);
                $currency = $curr->wallet;
            }

            $country_data = self::getPaymentData($currency);

            if ($currency != 'PEN') {
                return ['error' => 'Currency not supportted at the momment'];
            }

            // var_dump($country_data); exit;
            // $body = $this->buildPayout($country_data['country'], $amount, $currency, uuid(), $description, $beneficiaryId);
            $body = $this->buildPayoutPayload($beneficiaryId, $country_data['country'], $quoteId);

            $sample_hashedBody = hash('sha256', json_encode($body), false);
            $_data = $HTTPmethod . ':' . $resourcePath . $timestamp . ':' . $sample_hashedBody;
            $signature = hash_hmac('sha256', $_data, $apiSecret);

            // return $body; exit;
            $endpoint = $this->baseUrl . $resourcePath . $timestamp . '&signature=' . $signature;
            $payoutDataOther = $body;
            $response = Http::withHeaders([
                'monnet-api-key' => getenv('MONNET_API_TOKEN'),
                'Content-Type' => 'application/json',
            ])
                ->post($endpoint, $payoutDataOther)
                ->json();

            if (!is_array($response)) {
                $response = json_decode($response, true);
            }

            Log::info(json_encode(['request' => $body, 'response' => $response]));

            if (isset($response['output']) and isset($response['output']['stage']) and $response['output']['stage'] == 'IN_PROGRESS') {
                return ['message' => "Payout request submitted successfully. Current status: IN PROGRESS"];
            }
            return $response;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function payoutStatus($payoutId = null)
    {
        try {
            $apiSecret = getenv('MONNET_API_SECRET');
            $HTTPmethod = 'GET';
            $resourcePath = '/api/v1/125/payouts/' . $payoutId;
            $timestamp = '?timestamp=' . time();
            $body = ''; //request()->post();
            $sample_hashedBody = hash('sha256', '', false);
            $_data = $HTTPmethod . ':' . $resourcePath . $timestamp . ':' . $sample_hashedBody;
            $signature = hash_hmac('sha256', $_data, $apiSecret);
            $endpoint = $this->baseUrl . $resourcePath . $timestamp . '&signature=' . $signature;

            $response = Http::withHeaders([
                'monnet-api-key' => getenv('MONNET_API_TOKEN'),
                'Content-Type' => 'application/json',
            ])
                ->get($endpoint)
                ->json();

            Log::info(json_encode(['request' => $body, 'response' => $response]));
            return $response;
        } catch (\Throwable $th) {
            if (getenv('APP_DEBUG')) {
                return ['error' => $th->getTrace()];
            }
            return ['error' => $th->getMessage()];
        }
    }

    public function buildPayinPayload($amount, $currency = 'PEN', $quoteId = null)
    {
        $request = request();
        $user = $request->user();

        $payinCustomerRegion = $user->state;
        if ($currency == 'PEN') {
            $payinCustomerRegion = "Lima";
        }

        $idType = "DNI"; //$user->idType;
        if (!isset($idType) or null == $idType) {
            return ['error' => "Please update your ID type"];
        }
        $payment_data = self::getPaymentData($currency);
        $txn = $quoteId ?? uuid();
        $amount = convertIntToDecimal($amount);
        $key = $payment_data['merchantKey'];
        $merchantId = $payment_data['merchantId'];

        $verificationString = self::generateVerificationString($merchantId, $txn, $amount, $currency, $key);
        $data = [
            'payinMerchantID' => $merchantId,
            'payinAmount' => $amount,
            'payinCurrency' => $currency,
            'payinMerchantOperationNumber' => $txn,
            'payinMethod' => "BankTransfer",
            'payinVerification' => $verificationString,
            'payinCustomerName' => $user->firstName ?? "string",
            'payinCustomerLastName' => $user->lastName,
            'payinCustomerEmail' => $user->email,
            'payinCustomerPhone' => $user->phoneNumber,
            'payinCustomerTypeDocument' => $idType,
            'payinCustomerDocument' => $user->idNumber,
            'payinRegularCustomer' => $user->firstName ?? "string",
            'payinCustomerID' => $user->id,
            'payinLanguage' => 'EN',
            'payinExpirationTime' => 30,
            'payinDateTime' => date('Y-m-d'),
            'payinTransactionOKURL' => str_replace('http://', 'https://', route("callback.monnet.success", [$user->id, $txn])),
            'payinTransactionErrorURL' => str_replace('http://', 'https://', route("callback.monnet.failed", [$user->id, $txn])),
            'payinCustomerAddress' => $user->street ?? "string",
            'payinCustomerCity' => $user->city ?? "string",
            'payinCustomerRegion' => $payinCustomerRegion,
            'payinCustomerCountry' => $user->country ?? "string",
            'payinCustomerZipCode' => $user->zipCode ?? "string",
            'payinCustomerShippingName' => $user->name ?? "string",
            'payinCustomerShippingPhone' => $user->phoneNumber ?? "string",
            'payinCustomerShippingAddress' => $user->street ?? "string",
            'payinCustomerShippingCity' => $user->city ?? "string",
            'payinCustomerShippingRegion' => $user->state ?? "string",
            'payinCustomerShippingCountry' => $user->country ?? "string",
            'payinCustomerShippingZipCode' => $user->zipCode ?? "string",
            'payinProductID' => $txn,
            'payinProductDescription' => 'Wallet top up on ' . getenv('APP_NAME'),
            'payinProductAmount' => convertIntToDecimal($amount),
            'payinProductSku' => $txn,
            'payinProductQuantity' => 1,
            'URLMonnet' => 'https://cert.monnetpayments.com/api-payin/v3/online-payments',
            'typePost' => 'json',
        ];

        // echo response()->json($data); exit;
        return $data;
    }

    public function payin($quoteId, $amount, $currency, $type = 'send_money')
    {
        try {
            $request = request();
            $user = $request->user();
            if (!in_array($currency, ['PEN', 'MXN'])) {
                return ['error' => 'Invalid or unsupported Currency type'];
            }
            // $user->idType = "DNI";
            // $user->idNumber = "76482931";
            // $user->save();
            $requirement = [
                'DNI' => [
                    'currency' => 'PEN',
                    'document_value_key' => 'DNI',
                    'document_type_name' => 'Identity Document',
                    'document_validation_regex' => '/^\d{8}$/'
                ],
                'CE' => [
                    'currency' => 'PEN',
                    'document_value_key' => 'CE',
                    'document_type_name' => 'Foreign resident Card',
                    'document_validation_regex' => '/^[a-zA-Z0-9]{8,12}$/'
                ],
                'RUC' => [
                    'currency' => 'PEN',
                    'document_value_key' => 'RUC',
                    'document_type_name' => 'Tax Id Number',
                    'document_validation_regex' => '/^\d{9,10}$/'
                ],
                'PAS' => [
                    'currency' => 'PEN',
                    'document_value_key' => 'PAS',
                    'document_type_name' => 'Passport',
                    'document_validation_regex' => '/^\d{7,12}$/'
                ],
                'CURP' => [
                    'currency' => 'MXN',
                    'document_value_key' => 'CURP',
                    'document_type_name' => 'Unique Population Registry Code',
                    'document_validation_regex' => '/^\d{13,18}$/'
                ],
                'RFC' => [
                    'currency' => 'MXN',
                    'document_value_key' => 'RFC',
                    'document_type_name' => 'Federal taxpayer registration',
                    'document_validation_regex' => '/^\d{13}$/'
                ]
            ];

            if ($currency == 'PEN') {
                $allowedId = ['DNI', 'CE', 'RUC', 'PAS'];
                if (!in_array($user->idType, $allowedId)) {
                    return ['error' => 'Invalid or unsupported ID type'];
                }
                $payinCustomerRegion = "Lima";
            }

            if ($currency == 'MXN') {
                $allowedId = ['CURP', 'RFC'];
                if (!in_array($user->idType, $allowedId)) {
                    return ['error' => 'Invalid or unsupported ID type'];
                }
            }

            if (!preg_match($requirement[$user->idType]['document_validation_regex'], $user->idNumber)) {
                return ['error' => 'Invalid ID number'];
            }

            // if (!in_array($request->payinMethod, ['BankTransfer'])) {
            //     return ['error' => 'Unsupported payment method type'];
            // }


            $data = self::buildPayinPayload($amount, $currency, $quoteId);
            // return $request = Http::post('https://cert.monnetpayments.com/api-payin/v3/online-payments/', $data)->json();
            $request = $this->api_call($this->payinUrl, $data);

            $result = json_decode($request, true);

            // echo json_encode($result, JSON_PRETTY_PRINT); exit;

            if (strtolower($type) != 'deposit') {
                updateSendMoneyRawData($quoteId, [
                    'user_request' => $data,
                    'gateway_response' => $result,
                ]);
            } else {
                updateDepositRawData($quoteId, [
                    'user_request' => $data,
                    'gateway_response' => $result,
                ]);
            }

            $response = $result;

            if (empty($response)) {
                return ['error' => 'Response is empty'];
            }

            Log::info(json_encode(['country' => $currency, ['payload' => $data, 'response' => $response]]));

            if (isset($response['url']) && !empty($response['url']))
                return $response['url'];
            elseif (isset($response['url']) && empty($response['url']))
                return ['error' => "Please contact support with Errorcode: Monnt".base64_encode(time())];
            elseif (isset($response['payinErrorMessage']))
                return $response['payinErrorMessage'];
            else
                $response;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function buildPayoutPayload($beneficiaryId, $country, $quoteId)
    {
        $request = request();
        // $customer = Beneficiary::whereId($beneficiaryId)->whereUserId(active_user())->first();
        $beneficiaryPaymentMethod = BeneficiaryPaymentMethod::with('beneficiary')->whereId($beneficiaryId)->first();
        $customer = $beneficiaryPaymentMethod->beneficiary;
        $payment_data = $beneficiaryPaymentMethod->payment_data;
        // echo json_encode($beneficiaryPaymentMethod, JSON_PRETTY_PRINT); exit;
        $arr = [
            'country' => $country,
            'amount' => $request?->amount,
            'currency' => $payment_data?->currency,
            'orderId' => $quoteId,
            'description' => $request?->description ?? 'Payout from ' . getenv('APP_NAME'),
            'beneficiary' => [
                'userName' => user()?->bussinessName ?? "string",
                'name' => user()?->firstName ?? "string",
                'lastName' => user()?->lastName ?? "string",
                'email' => user()?->email ?? "string",
                'document' => [
                    'type' => $payment_data?->beneficiary?->document?->type ?? "string",
                    'number' => $payment_data?->beneficiary?->document?->number ?? "string",
                ],
                'address' => [
                    'street' => $beneficiaryPaymentMethod?->address?->street,
                    'houseNumber' => $beneficiaryPaymentMethod?->houseNumber,
                    'additionalInfo' => $beneficiaryPaymentMethod?->address?->additionalInfo,
                    'city' => $beneficiaryPaymentMethod?->address?->city,
                    'province' => $beneficiaryPaymentMethod?->address?->province,
                    'zipCode' => $beneficiaryPaymentMethod?->address?->zipCode,
                ],
            ],
            'destination' => [
                'bankAccount' => [
                    'bankCode' => $payment_data?->destination?->bankAccount?->bankCode ?? "string",
                    'accountType' => $payment_data?->destination?->bankAccount?->accountType ?? "string",
                    'accountNumber' => $payment_data?->destination?->bankAccount?->accountNumber ?? "string",
                    'alias' => $payment_data?->destination?->bankAccount?->alias ?? "string",
                    'cbu' => $payment_data?->destination?->bankAccount?->cbu ?? "string",
                    'cci' => $payment_data?->destination?->bankAccount?->cci ?? "string",
                    'clabe' => $payment_data?->destination?->bankAccount?->clave ?? "string",
                    'location' => [
                        'street' => $beneficiaryPaymentMethod?->address?->street,
                        'houseNumber' => $beneficiaryPaymentMethod?->houseNumber,
                        'additionalInfo' => $beneficiaryPaymentMethod?->address?->additionalInfo,
                        'city' => $beneficiaryPaymentMethod?->address?->city,
                        'province' => $beneficiaryPaymentMethod?->address?->province,
                        'zipCode' => $beneficiaryPaymentMethod?->address?->zipCode,
                        'country' => $country ?? "string",
                    ],
                ],
            ],
        ];

        // echo response()->json($arr); exit;
        return removeEmptyArrays($arr);
    }

    private function buildPayout($country, $amount, $currency, $orderId, $description, $beneficiaryId)
    {
        try {
            $customer = Beneficiary::whereId($beneficiaryId)->whereUserId(auth()->id())->first();
            if (!$customer) {
                return get_error_response(['error' => 'Beneficiary not found']);
            }

            $beneficiary = $customer['beneficiary'];
            $bank = $customer['payment_object']['bankAccount'];

            $bankAccount = [
                'bankCode' => $bank['bankCode'],
                'accountType' => $bank['accountType'],
            ];

            // Add appropriate bank account details based on the country's requirements
            $body = [
                'country' => $country,
                'amount' => $amount,
                'currency' => $currency,
                'orderId' => $orderId,
                'description' => $description,
                'beneficiary' => [
                    'name' => $beneficiary['name'],
                    'lastName' => $beneficiary['lastName'] ?? $beneficiary['name'],
                    'email' => $beneficiary['email'],
                    'document' => [
                        'type' => $beneficiary['document']['type'],
                        'number' => $beneficiary['document']['number'],
                    ],
                ],
                'destination' => [
                    'bankAccount' => $bankAccount,
                ],
            ];
            if ($currency == 'ARS') {
                $body['destination']['bankAccount']['cbu'] = $bank['cbu'];
            } elseif ($currency == 'MXN') {
                $body['destination']['bankAccount']['clabe'] = $bank['clave'];
            } elseif (!empty($bank['accountNumber'])) {
                $body['destination']['bankAccount']['accountNumber'] = $bank['accountNumber'];
            } elseif (isset($bank['accountType']) && $bank['accountType'] == 4) {
                // set document number as accountNumber
                $body['destination']['bankAccount']['accountNumber'] = $beneficiary['document']['number'];
            }
            // echo json_encode($body, JSON_PRETTY_PRINT); exit;
            return $body;
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function api_call(string $endpoint = '', array $payload = [])
    {
        $apiKey = getenv('MONNET_PERU');
        $endpoint = $this->payinUrl;
        $requestBody = json_encode($payload);
        $headers = ['Content-Type: application/json', 'monnet-api-key: ' . $apiKey];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
        }
        curl_close($ch);
        return $response;
    }

    private function generateVerificationString($payinMerchantID, $payinMerchantOperationNumber, $payinAmount, $payinCurrency, $KeyMonnet)
    {
        $concatenatedString = $payinMerchantID . $payinMerchantOperationNumber . $payinAmount . $payinCurrency . $KeyMonnet;
        $verificationString = openssl_digest($concatenatedString, 'sha512');

        return $verificationString;
    }

    public function webhook(Request $request)
    {
    }

    public function getPaymentData($currency)
    {
        switch ($currency) {
            case 'COP':
                $data = [
                    'merchantId' => getenv('MONNET_COLUMBIA_ID'),
                    'merchantKey' => getenv('MONNET_COLUMBIA'),
                    'country' => 'COL',
                ];
                break;

            case 'PEN':
                $data = [
                    'merchantId' => getenv('MONNET_PERU_ID'),
                    'merchantKey' => getenv('MONNET_PERU'),
                    'country' => 'PER',
                ];
                break;

            case 'USD':
                $data = [
                    'merchantId' => getenv('MONNET_ECUADO_ID'),
                    'merchantKey' => getenv('MONNET_ECUADO'),
                    'country' => 'USD',
                ];
                break;

            case 'CLP':
                $data = [
                    'merchantId' => getenv('MONNET_CHILE_ID'),
                    'merchantKey' => getenv('MONNET_CHILE'),
                    'country' => 'CHL',
                ];
                break;

            case 'ARS':
                $data = [
                    'merchantId' => getenv('MONNET_ARGENTINA_ID'),
                    'merchantKey' => getenv('MONNET_ARGENTINA'),
                    'country' => 'ARG',
                ];
                break;

            case 'MXN':
                $data = [
                    'merchantId' => getenv('MONNET_MEXICO_ID'),
                    'merchantKey' => getenv('MONNET_MEXICO'),
                    'country' => 'MEX',
                ];
                break;

            default:
                // DEFFAULT TO USD
                $data = [
                    'merchantId' => getenv('MONNET_ECUADO_ID'),
                    'merchantKey' => getenv('MONNET_ECUADO'),
                    'country' => 'USD',
                ];
                break;
        }

        return $data;
    }

    private function peruPayinMethod()
    {
        $data = [
            'payinMerchantID' => '00',
            'payinAmount' => '00.00',
            'payinCurrency' => 'PEN',
            'payinMerchantOperationNumber' => '0000',
            'payinMethod' => 'BankTransfer',
            'payinVerification' => 'string',
            'payinCustomerName' => 'string',
            'payinCustomerLastName' => 'string',
            'payinCustomerEmail' => 'test@test.com',
            'payinCustomerPhone' => '0000',
            'payinCustomerTypeDocument' => 'DNI',
            'payinCustomerDocument' => '00000000',
            'payinRegularCustomer' => 'string',
            'payinCustomerID' => 'string',
            'payinDiscountCoupon' => 'string',
            'payinLanguage' => 'ES',
            'payinExpirationTime' => '000',
            'payinDateTime' => 'YYYY-MM-DD',
            'payinTransactionOKURL' => 'https://test.com',
            'payinTransactionErrorURL' => 'https://test.com',
            'payinFilterBy' => 'string',
            'payinCustomerAddress' => 'string',
            'payinCustomerCity' => 'string',
            'payinCustomerRegion' => 'string',
            'payinCustomerCountry' => 'Peru',
            'payinCustomerZipCode' => '0000',
            'payinCustomerShippingName' => 'string',
            'payinCustomerShippingPhone' => '0000',
            'payinCustomerShippingAddress' => 'string',
            'payinCustomerShippingCity' => 'string',
            'payinCustomerShippingRegion' => 'string',
            'payinCustomerShippingCountry' => 'Peru',
            'payinCustomerShippingZipCode' => '0000',
            'payinProductID' => '0000',
            'payinProductDescription' => 'string',
            'payinProductAmount' => '0000',
            'payinProductSku' => 'string',
            'payinProductQuantity' => '0000',
            'URLMonnet' => 'https://cert.monnetpayments.com/api-payin/v1/online-payments',
            'typePost' => 'json',
        ];
    }
}
