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
use App\Models\CheckoutModel;
use App\Models\Country;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Advcash\app\Http\Controllers\AdvcashController;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Bitso\app\Http\Controllers\BitsoController as ControllersBitsoController;
use Modules\Bitso\app\Services\BitsoServices;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\CustomerVirtualCards;
use Modules\Flow\app\Http\Controllers\FlowController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletController;
use Modules\VitaWallet\app\Http\Controllers\VitaWalletTestController;
use Spatie\WebhookServer\WebhookCall;
use App\Http\Controllers\ManageDBController;

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
    $user = User::whereEmail('towojuads@gmail.com')->first();
    // $user->
    
    // $floid = new FlowController();
    // $result = $floid->makePayment('1234567890', 100, 'PEN');
    // dd($result);

    // $payouts = payoutMethods::where('gateway', 'vitawallet')->pluck('id')->toArray();
    // $forms = BeneficiaryFoems::whereIn('gateway_id', $payouts)->get();
    // return response()->json([
    //     'data' => $forms,
    //     "332" => payoutMethods::whereId('332')->first()
    // ]);

    // $methods = payoutMethods::whereNotIn('id', $forms)->where('payment_mode', 'Advcash_card')->get();
    // foreach($methods as $key => $method) {
    //     $formStructure = [
    //         "gateway_id" => $method->id,
    //         "currency" => $method->currency,
    //         "form_data" => [
    //             "payment_data" => [
    //                 [
    //                     "key" => "cardNumber",
    //                     "name" => "Card Number",
    //                     "type" => "text",
    //                     "description" => "The credit card number."
    //                 ],
    //                 [
    //                     "key" => "expiryMonth",
    //                     "name" => "Expiry Month",
    //                     "type" => "text",
    //                     "description" => "Month of expiration for the card."
    //                 ],
    //                 [
    //                     "key" => "expiryYear",
    //                     "name" => "Expiry Year",
    //                     "type" => "text",
    //                     "description" => "Year of expiration for the card."
    //                 ],
    //                 [
    //                     "key" => "note",
    //                     "name" => "Note",
    //                     "type" => "text",
    //                     "description" => "Additional notes for the payment."
    //                 ],
    //                 [
    //                     "key" => "savePaymentTemplate",
    //                     "name" => "Save Payment Template",
    //                     "type" => "checkbox",
    //                     "description" => "Option to save this payment method as a template."
    //                 ],
    //                 [
    //                     "key" => "cardHolder",
    //                     "name" => "Card Holder",
    //                     "type" => "text",
    //                     "description" => "Name of the cardholder."
    //                 ],
    //                 [
    //                     "key" => "cardHolderCountry",
    //                     "name" => "Card Holder Country",
    //                     "type" => "text",
    //                     "description" => "Country of the cardholder."
    //                 ],
    //                 [
    //                     "key" => "cardHolderCity",
    //                     "name" => "Card Holder City",
    //                     "type" => "text",
    //                     "description" => "City of the cardholder."
    //                 ],
    //                 [
    //                     "key" => "cardHolderDOB",
    //                     "name" => "Card Holder Date of Birth",
    //                     "type" => "date",
    //                     "description" => "Date of birth of the cardholder."
    //                 ],
    //                 [
    //                     "key" => "cardHolderMobilePhoneNumber",
    //                     "name" => "Card Holder Mobile Phone Number",
    //                     "type" => "tel",
    //                     "description" => "Mobile phone number of the cardholder."
    //                 ]
    //             ]
    //         ]
    //     ];
        
    //     $forms[] = BeneficiaryFoems::firstOrCreate($formStructure);
    // }
    // return response()->json($forms);
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
