<?php

use App\Http\Controllers\BitsoController;
use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Business\VirtualAccountsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CryptoWalletsController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\KycServiceController;
use App\Http\Controllers\LocalPaymentWebhookController;
use App\Http\Controllers\PaxosController;
use App\Models\BeneficiaryFoems;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use App\Services\OnrampService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Advcash\app\Http\Controllers\AdvcashController;
use Modules\Bitso\app\Http\Controllers\BitsoController as ControllersBitsoController;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletTestController;
use App\Http\Controllers\CoinbaseOnrampController;
use App\Http\Controllers\TransFiController;
use App\Models\Business;
use App\Models\User;
use Modules\Customer\app\Http\Controllers\DojahVerificationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('mi', function () {
    \DB::statement('SET FOREIGN_KEY_CHECKS=0;'); 
    BeneficiaryFoems::truncate();
    $currencies = ['PHP', 'IDR', 'VND', 'MYR'];
    foreach ($currencies as $currency) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.transfi.com/v2/payment-methods?currency={$currency}&limit=1000&direction=withdraw",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Set timeout to avoid infinite waits
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic eWF0aXZvOmRENWsxNjJhOHhLYU04'
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        // Handle cURL errors
        if ($curlError) {
            Log::error("cURL Error: {$curlError}");
            continue;
        }

        // Handle HTTP errors
        if ($httpCode !== 200) {
            Log::error("HTTP Error: {$httpCode} for currency {$currency}");
            continue;
        }

        // Decode JSON response
        $result = json_decode($response, true);
        if (!isset($result['status']) || $result['status'] !== 'success' || !isset($result['paymentMethods'])) {
            Log::error("Invalid API response for currency {$currency}: " . json_encode($result));
            continue;
        }

        // Loop through payment methods
        foreach ($result['paymentMethods'] as $method) {
            if (!isset($method['paymentCode']) || !isset($method['additionalDetails']['accountNumber'])) {
                Log::warning("Missing required fields in payment method: " . json_encode($method));
                continue;
            }

            $gateway = PayoutMethods::where([
                'gateway' => 'transfi',
                'currency' => $currency,
                'payment_method_code' => $method['paymentCode'],
            ])->first();

            if (!$gateway) {
                Log::warning("No matching gateway found for payment method: " . $method['paymentCode']);
                continue;
            }

            // Extract additional details
            $accountDetails = $method['additionalDetails']['accountNumber'] ?? [];
            if (!isset($accountDetails['min'], $accountDetails['max'], $accountDetails['pattern'])) {
                Log::warning("Incomplete account details for payment method: " . $method['paymentCode']);
                continue;
            }

            $arr = [
                "gateway_id" => $gateway->id,
                "currency" => $currency,
                "form_data" => [
                    "payment_data" => [
                        [
                            "key" => "accountNumber",
                            "name" => "Account Number",
                            "type" => "text",
                            "min" => $accountDetails['min'],
                            "max" => $accountDetails['max'],
                            "value" => "",
                            "required" => true,
                            "pattern" => $accountDetails['pattern'], // Fixed syntax
                        ]
                    ]
                ]
            ];

            BeneficiaryFoems::updateOrCreate(
                ["gateway_id" => $gateway->id],
                $arr
            );
        }
    }
});














Route::view('onramp', 'welcome');


Route::get('/', function () {
    return redirect()->to('https://yativo.com');
});


Route::any('/coinbase/onramp/token', [CoinbaseOnrampController::class, 'getSessionToken']);
Route::any('/coinbase/onramp/url', [CoinbaseOnrampController::class, 'generateOnrampUrl']);

Route::domain(env('CHECKOUT_DOMAIN'))->group(function () {
    Route::get('process-payin/{id}/paynow', [CheckoutController::class, 'show'])->name('checkout.url');
});


Route::get('/wallets/{uuid}', [VitaWalletTestController::class, 'getWalletByUUID']);
Route::get('/wallets', [VitaWalletTestController::class, 'listWallets']);
Route::post('/transactions', [VitaWalletTestController::class, 'createTransaction']);
Route::post('/withdrawals', [VitaWalletTestController::class, 'createWithdrawal']);
Route::post('/vita-send', [VitaWalletTestController::class, 'createVitaSend']);
Route::get('/transactions', [VitaWalletTestController::class, 'listTransactions']);
Route::get('/wallet-transactions/{uuid}', [VitaWalletTestController::class, 'listWalletTransactions']);

Route::any('callback/webhook/transfi', [TransFiController::class, 'processWebhook'])->name('transfi.callback.success');

Route::group([], function () {
    Route::get('checkout/advcash/{checkout_hash}', [AdvcashController::class, 'checkout'])->name('advcash.checkout.url');
});

// Route::any('bitso/withdrawal', [BitsoController::class, 'initiateWithdrawal'])->name('bitso.withdrawal');

Route::group([], function () {
    Route::get('callback/payIn/onramp', [OnrampService::class, 'payInCallback'])->name('onramp.payIn.callback');
    Route::get('callback/payOutn/onramp', [OnrampService::class, 'payOutCallback'])->name('onramp.payOut.callback');

    Route::any('callback/webhook/coinpayments', [CryptoWalletsController::class, 'wallet_webhook']);
    Route::any('callback/webhook/local-payments', [LocalPaymentWebhookController::class, 'handle']);
    Route::any('callback/webhook/bitso', [ControllersBitsoController::class, 'deposit_webhook'])->name('bitso.cop.deposit');

    // Bridge webhook callback
    Route::any('callback/webhook/bridge', [BridgeController::class, 'BridgeWebhook'])->name('bridge.callback.success');
    Route::any('callback/webhook/customer-kyc', [DojahVerificationController::class, 'KycWebhook'])->name('kyc.callback.success');


    Route::any('callback/webhook/vitawallet', [VitaWalletController::class, 'callback'])->name('vitawallet.callback.success');
    Route::any('callback/webhook/deposit/vitawallet/{quoteId}', [VitaWalletController::class, 'deposit_callback'])->name('vitawallet.deposit.callback.success');

    Route::any('callback/webhook/floid', [FlowController::class, 'callback'])->name('floid.callback.success');
    Route::any('callback/webhook/floid-redirect', [FlowController::class, 'callback'])->name('floid.callback.redirect');

    Route::post("callback/webhook/virtual-account-webhook", [VirtualAccountsController::class, 'virtualAccountWebhook'])->name('business.virtual-account.virtualAccountWebhook');
    Route::any('callback/wallet/webhook/{userId}/{currency}', [CryptoWalletsController::class, 'walletWebhook'])->name('crypto.wallet.address.callback');
})->withoutMiddleware(VerifyCsrfToken::class);

Route::any('cron', [CronController::class, 'index'])->name('cron.index');


Route::post('process-paxos', [PaxosController::class, 'processPexos'])->name('process.pexos');


Route::match(['get', 'post'], 'login', function () {
    $json = [
        "endpoint" => request()->fullUrl(),
        "ip" => request()->ip(),
        "userAgent" => request()->userAgent(),
        "payload" => request()->all(),
    ];
    return get_error_response([
        "error" => "Sorry we could not find a matching route for this request",
        "info" => $json
    ]);
});


Route::fallback(function () {
    $json = [
        "endpoint" => request()->fullUrl(),
        "ip" => request()->ip(),
        "userAgent" => request()->userAgent(),
        "payload" => request()->all(),
    ];
    Log::channel('missing_route')->error(json_encode($json));
    return get_error_response([
        "error" => "Sorry we could not find a matching route for this request",
        "info" => $json
    ]);
});
