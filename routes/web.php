<?php

use App\Http\Controllers\BitsoController;
use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Business\VirtualAccountsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClabeController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CronDepositController;
use App\Http\Controllers\CryptoWalletsController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\KycServiceController;
use App\Http\Controllers\LocalPaymentWebhookController;
use App\Http\Controllers\PaxosController;
use App\Http\Controllers\PaymentController;
use App\Models\BeneficiaryFoems as BeneficiaryForms;
use App\Models\CustomPricing;
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
use Modules\Bitso\app\Services\BitsoServices;
use Modules\Customer\app\Http\Controllers\CustomerController;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletTestController;
use App\Http\Controllers\CoinbaseOnrampController;
use App\Http\Controllers\TransFiController;
use App\Models\Business;
use App\Models\User;
use Bavix\Wallet\Models\Wallet;
use App\Models\Deposit;
use Modules\Customer\app\Http\Controllers\DojahVerificationController;
use Modules\Customer\app\Http\Controllers\CustomerVirtualCardsController;
use App\Models\Business\VirtualAccount;
use Illuminate\Support\Facades\Schema;
use App\Models\localPaymentTransactions;
use App\Http\Controllers\BitHonorController;
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

// Route::get('p-gateways', function(Request $request){
//     $type = request()->type ?? 'payin';
//     if($type == 'payin') {
//         $response = PayinMethods::query();
//     } else {
//         $response = payoutMethods::query();
//     }
//     $result = $response->get(['id', 'method_name', 'country', 'currency', 'base_currency']);
//     return response()->json($result);
// });



// dd(CustomPricing::all());

// // routes/web.php
// Route::get('export-tables', [\App\Http\Controllers\TableExportController::class, 'index'])->name('tables.index');
// Route::post('export-tables', [\App\Http\Controllers\TableExportController::class, 'export'])->name('tables.export');

if(!Schema::hasColumn("customer_virtual_cards", "card_name")) {
    Schema::table("customer_virtual_cards", function(Blueprint $table) {
        $table->string('card_name')->nullable();
        $table->string('card_status')->default('pending');
        $table->string('card_brand')->default('visa');
    });
}


// routes/web.php or routes/api.php
Route::get('bridge-vc-update-all', [BridgeController::class, 'updateAllVirtualAccounts']);



Route::any('register-payment', [PaymentController::class, "registerPayment"])->withoutMiddleware(VerifyCsrfToken::class);
Route::any('stp-payout', [PaymentController::class, "payout"])->withoutMiddleware(VerifyCsrfToken::class);


Route::view('onramp', 'welcome');

Route::get('/', function () {
    return redirect()->to('https://yativo.com');
});

// Route::post('09039clone', function(){
//     $id = request('id');
//     $original = BeneficiaryForms::where('gateway_id', $id)->first();
//     if(!$original) {
//         return response()->json([
//             "error" => "Gateway_id $id not found"
//         ]);
//     }
//     $clone = $original->replicate();
//     $clone->gateway_id = request('gateway');
//     $clone->save();

//     return response()->json([
//         'message' => 'Form cloned successfully',
//         'original_id' => $original->id,
//         'clone_id' => $clone->id,
//         'clone' => $clone,
//     ]);
// })->withoutMiddleware(VerifyCsrfToken::class);

Route::get('v', [CronDepositController::class, "vitawallet"]);

Route::any('/coinbase/onramp/token', [CoinbaseOnrampController::class, 'getSessionToken']);
Route::any('/coinbase/onramp/url', [CoinbaseOnrampController::class, 'generateOnrampUrl']);

Route::domain(env('CHECKOUT_DOMAIN'))->group(function () {
    Route::get('process-payin/{id}/paynow', [CheckoutController::class, 'show'])->name('checkout.url');
    Route::get('kyc/update-biodata/{customerId}', [DojahVerificationController::class, 'kycStatus'])->name('checkout.kyc');
    Route::get('kyc/init/{customerId}', [CustomerController::class, 'initKyc'])->name('checkout.kyc.init');
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
    Route::any('callback/webhook/yativo-crypto', [CryptoWalletsController::class, 'yativo_webhook']);
    Route::any('callback/webhook/local-payments', [LocalPaymentWebhookController::class, 'handle']);
    Route::any('callback/webhook/bitso', [ControllersBitsoController::class, 'deposit_webhook'])->name('bitso.cop.deposit');

    // Bridge webhook callback
    Route::any('callback/webhook/bridge', [BridgeController::class, 'BridgeWebhook'])->name('bridge.callback.success');
    Route::any('callback/webhook/customer-kyc', [DojahVerificationController::class, 'KycWebhook'])->name('kyc.callback.success');


    Route::any('callback/webhook/vitawallet', [VitaWalletController::class, 'callback'])->name('vitawallet.callback.success');
    Route::any('callback/webhook/deposit/vitawallet/{quoteId}', [VitaWalletController::class, 'deposit_callback'])->name('vitawallet.deposit.callback.success');

    Route::any('callback/webhook/floid', [FlowController::class, 'callback'])->name('floid.callback.success');
    Route::any('callback/webhook/floid-redirect', [FlowController::class, 'callback'])->name('floid.callback.redirect');

    Route::any('callback/webhook/stp-payout', [ClabeController::class, 'handlePayout'])->name('stp.callback.payout');
    Route::any('callback/webhook/stp-payin', [ClabeController::class, 'handleDeposit'])->name('stp.callback.deposit');

    Route::post("callback/webhook/virtual-account-webhook", [VirtualAccountsController::class, 'virtualAccountWebhook'])->name('business.virtual-account.virtualAccountWebhook');
    Route::any('callback/wallet/webhook/yativo/crypto', [CryptoWalletsController::class, 'walletWebhook'])->name('crypto.wallet.address.callback');

    //Bitnob webhook url 
    Route::any('callback/webhook/bitnob', [CustomerVirtualCardsController::class, 'webhook']);
    Route::any('callback/webhook/bithonor', [BitHonorController::class, 'webhook']);
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
