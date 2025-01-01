<?php

use App\Http\Controllers\BitsoController;
use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Business\VirtualAccountsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CryptoWalletsController;
use App\Http\Controllers\KycServiceController;
use App\Http\Controllers\LocalPaymentWebhookController;
use App\Http\Controllers\TransactionRecordController;
use App\Models\Admin;
use App\Models\ApiLog;
use App\Models\BeneficiaryFoems;
use App\Models\Business;
use App\Models\Business\VirtualAccount;
use App\Models\BusinessUbo;
use App\Models\CheckoutModel;
use App\Models\Country;
use App\Models\Deposit;
use App\Models\ExchangeRate;
use App\Models\localPaymentTransactions;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use App\Models\Track;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Configuration;
use App\Services\OnrampService;
use App\Services\VitaBusinessAPI;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Advcash\app\Http\Controllers\AdvcashController;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\BinancePay\app\Models\BinancePay;
use Modules\Bitso\app\Http\Controllers\BitsoController as ControllersBitsoController;
use Modules\Bitso\app\Services\BitsoServices;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\CustomerVirtualCards;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\ShuftiPro\app\Models\ShuftiPro;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletTestController;
use Spatie\WebhookServer\WebhookCall;
use App\Http\Controllers\ManageDBController;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Http\Controllers\CoinbaseOnrampController;

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


Route::view('onramp', 'welcome');


Route::get('/', function () {
    // return redirect()->to('https://yativo.com');
    $countries = [
        ["country" => "Austria", "iso2" => "AT", "currency" => "EUR"],
        ["country" => "Belgium", "iso2" => "BE", "currency" => "EUR"],
        ["country" => "Cyprus", "iso2" => "CY", "currency" => "EUR"],
        ["country" => "Estonia", "iso2" => "EE", "currency" => "EUR"],
        ["country" => "Finland", "iso2" => "FI", "currency" => "EUR"],
        ["country" => "France", "iso2" => "FR", "currency" => "EUR"],
        ["country" => "Germany", "iso2" => "DE", "currency" => "EUR"],
        ["country" => "Greece", "iso2" => "GR", "currency" => "EUR"],
        ["country" => "Ireland", "iso2" => "IE", "currency" => "EUR"],
        ["country" => "Italy", "iso2" => "IT", "currency" => "EUR"],
        ["country" => "Latvia", "iso2" => "LV", "currency" => "EUR"],
        ["country" => "Lithuania", "iso2" => "LT", "currency" => "EUR"],
        ["country" => "Luxembourg", "iso2" => "LU", "currency" => "EUR"],
        ["country" => "Malta", "iso2" => "MT", "currency" => "EUR"],
        ["country" => "Netherlands", "iso2" => "NL", "currency" => "EUR"],
        ["country" => "Portugal", "iso2" => "PT", "currency" => "EUR"],
        ["country" => "Slovakia", "iso2" => "SK", "currency" => "EUR"],
        ["country" => "Slovenia", "iso2" => "SI", "currency" => "EUR"],
        ["country" => "Spain", "iso2" => "ES", "currency" => "EUR"]
    ];

    foreach($countries as $k => $v) {
        $get_country = Country::where('iso2', $v['iso2'])->first();
        // add payin methods via transfi
    }
});

Route::get('omotowoju', function () {
    // $bridge = new BridgeController();
    // $bridgeCustomerId = "7d5b9315-796e-4d4d-b771-1ee5997e4abf";
    // $createWallet = $bridge->getCustomerBridgeWallet();
    // dd($createWallet);
    // return response()->json(['message' => CheckoutModel::all()]);
    // $table = 'tracks'; // Replace with your table name
    // $columns = DB::getSchemaBuilder()->getColumnListing($table);
    // dd($columns);

    // $response = Http::withHeaders([
    //     'Content-Type' => 'application/json',
    //     'Api-Key' => env('BRIDGE_API_KEY'),
    //     // 'Idempotency-Key' => generate_uuid()
    // ])->get(env('BRIDGE_BASE_URL') . 'v0/kyc_links/5a825002-d42b-494a-b1de-b954e42d630b');

    // if ($response->failed()) {
    //     return get_error_response(['error' => $response->json()]);
    // }

    // return get_success_response($response->json());

});




Route::any('/coinbase/onramp/token', [CoinbaseOnrampController::class, 'getSessionToken']);
Route::any('/coinbase/onramp/url', [CoinbaseOnrampController::class, 'generateOnrampUrl']);




Route::domain(env('CHECKOUT_DOMAIN'))->group(function () {
    Route::get('process-payin/{id}/paynow', [CheckoutController::class, 'show'])->name('checkout.url');
});

Route::domain(env('KYC_DOMAIN'))->group(function () {
    Route::get('process-payin/{id}/paynow', [KycServiceController::class, 'init'])->name('checkout.url');
});


Route::get('/wallets/{uuid}', [VitaWalletTestController::class, 'getWalletByUUID']);
Route::get('/wallets', [VitaWalletTestController::class, 'listWallets']);
Route::post('/transactions', [VitaWalletTestController::class, 'createTransaction']);
Route::post('/withdrawals', [VitaWalletTestController::class, 'createWithdrawal']);
Route::post('/vita-send', [VitaWalletTestController::class, 'createVitaSend']);
Route::get('/transactions', [VitaWalletTestController::class, 'listTransactions']);
Route::get('/wallet-transactions/{uuid}', [VitaWalletTestController::class, 'listWalletTransactions']);


Route::get('clear', function () {
    // Artisan::call('config:clear');
    // Artisan::call('cache:clear');
    // Artisan::call('view:clear');
    // Artisan::call('route:clear');

    // return response()->json(['result' => true]);
});


Route::get('payouts', function () {
    $gateways = payoutMethods::whereGateway('vitawallet')->get();
    return response()->json($gateways);
});


Route::group([], function () {
    Route::get('checkout/advcash/{checkout_hash}', [AdvcashController::class, 'checkout'])->name('advcash.checkout.url');
});

Route::any('bitso/withdrawal', [BitsoController::class, 'initiateWithdrawal'])->name('bitso.withdrawal');

Route::group([], function () {
    Route::get('callback/payIn/onramp', [OnrampService::class, 'payInCallback'])->name('onramp.payIn.callback');
    Route::get('callback/payOutn/onramp', [OnrampService::class, 'payOutCallback'])->name('onramp.payOut.callback');

    Route::any('callback/webhook/coinpayments', [CryptoWalletsController::class, 'wallet_webhook']);
    Route::any('callback/webhook/local-payments', [LocalPaymentWebhookController::class, 'handle']);
    Route::any('callback/webhook/bitso', [ControllersBitsoController::class, 'deposit_webhook'])->name('bitso.cop.deposit');

    Route::any('callback/webhook/vitawallet', [VitaWalletController::class, 'callback'])->name('vitawallet.callback.success');
    Route::any('callback/webhook/deposit/vitawallet/{quoteId}', [VitaWalletController::class, 'deposit_callback'])->name('vitawallet.deposit.callback.success');

    Route::any('callback/webhook/floid', [FlowController::class, 'callback'])->name('floid.callback.success');
    Route::any('callback/webhook/floid-redirect', [FlowController::class, 'getPaymentStatus'])->name('floid.callback.redirect');

    Route::post("callback/webhook/virtual-account-webhook", [VirtualAccountsController::class, 'virtualAccountWebhook'])->name('business.virtual-account.virtualAccountWebhook');
    Route::any('callback/wallet/webhook/{userId}/{currency}', [CryptoWalletsController::class, 'walletWebhook'])->name('crypto.wallet.address.callback');
})->withoutMiddleware(VerifyCsrfToken::class);

Route::any('cron', [CronController::class, 'index'])->name('cron.index');


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
