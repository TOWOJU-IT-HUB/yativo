<?php

use App\Http\Controllers\BitsoController;
use App\Http\Controllers\Business\VirtualAccountsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CryptoWalletsController;
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

Route::get('/', function () {
    $model = new User();
    return response()->json($model->where('email', 'towojuads@gmail.com')->first());
    // $tableName = $model->getTable();

    // Get all columns in the table
    // $columns = Schema::getColumnListing($tableName);

    // dd($columns);
    // return redirect()->to('https://yativo.com');

    // $response = Http::withHeaders([
    //     'Api-Key' => 'sk-test-bff33685a0aa22973f54bef2f8a814de',
    //     'accept' => 'application/json'
    // ])->get('https://api.sandbox.bridge.xyz/v0/customers');

    // if ($response->successful()) {
    //     return $response->json();
    // } else {
    //     return response()->json(['error' => $response->body()], $response->status());
    // }


    // $customerId = request()->customer_id;
    // $curl = curl_init();

    // curl_setopt_array($curl, [
    //     CURLOPT_URL => "https://api.sandbox.bridge.xyz/v0/customers/$customerId/virtual_accounts",
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_ENCODING => "",
    //     CURLOPT_MAXREDIRS => 10,
    //     CURLOPT_TIMEOUT => 30,
    //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //     CURLOPT_CUSTOMREQUEST => "POST",
    //     CURLOPT_POSTFIELDS => json_encode([
    //         'developer_fee_percent' => '0.1',
    //         'source' => [
    //             'currency' => 'usd'
    //         ],
    //         'destination' => [
    //             'currency' => 'usdc',
    //             'payment_rail' => 'polygon',
    //             'address' => '0xdeadbeef'
    //         ]
    //     ]),
    //     CURLOPT_HTTPHEADER => [
    //         "Api-Key: sk-test-bff33685a0aa22973f54bef2f8a814de",
    //         "accept: application/json",
    //         "content-type: application/json",
    //         "Idempotency-Key: ".generate_uuid(),
    //     ],
    // ]);

    // $response = curl_exec($curl);
    // $err = curl_error($curl);

    // curl_close($curl);

    // if ($err) {
    //     echo "cURL Error #:" . $err;
    // } else {
    //     echo $response;
    // }

    // return response()->json($data);


    // $response = Http::withHeaders([
    //     'Content-Type' => 'application/json',
    //     'Api-Key' => env('BRIDGE_API_KEY'),
    //     'Idempotency-Key' => generate_uuid(),
    // ])->post(env('BRIDGE_BASE_URL') . 'v0/kyc_links', [
    //             'full_name' => 'Emmanuel Adedayo Towoju',
    //             'email' => 'towojudas@gmail.com',
    //             'type' => 'individual', // or 'business'
    //             'endorsements' => ['sepa'],
    //             'redirect_uri' => 'https://api.yativo.com/kyc-callback',
    //         ]);

    // if ($response->successful()) {
    //     // Handle the response
    //     $data = $response->json();
    //     Log::info('Response:', $data);
    //     return response()->json($data);
    // } else {
    //     // Handle the error
    //     $error = $response->json();
    //     Log::error('Error:', $error);
    // }

});


// Route::prefix('db')->group(function () {
//     Route::post('backup', [ManageDBController::class, 'backup'])->name('db.backup');
//     Route::post('restore', [ManageDBController::class, 'restore'])->name('db.restore');
//     Route::post('drop-tables', [ManageDBController::class, 'dropAllTables'])->name('db.dropTables');
// });


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
