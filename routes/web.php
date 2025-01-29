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
use App\Models\payoutMethods;
use App\Services\OnrampService;
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


Route::view('onramp', 'welcome');


Route::get('/', function () {
    return redirect()->to('https://yativo.com');
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


Route::get('clear', function () {});



Route::any('callback/webhook/transfi', function () {
    $incoming = request()->all();
    Log::error("TRansfi", (array) $incoming);
})->name('transfi.callback.success');


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

    // Bridge webhook callback
    Route::any('callback/webhook/bridge', [BridgeController::class, 'BridgeWebhook'])->name('bridge.callback.success');
    Route::any('callback/webhook/customer-kyc', [DojahVerificationController::class, 'KycWebhook'])->name('kyc.callback.success');


    Route::any('callback/webhook/vitawallet', [VitaWalletController::class, 'callback'])->name('vitawallet.callback.success');
    Route::any('callback/webhook/deposit/vitawallet/{quoteId}', [VitaWalletController::class, 'deposit_callback'])->name('vitawallet.deposit.callback.success');

    Route::any('callback/webhook/floid', [FlowController::class, 'callback'])->name('floid.callback.success');
    Route::any('callback/webhook/floid-redirect', [FlowController::class, 'getPaymentStatus'])->name('floid.callback.redirect');

    Route::post("callback/webhook/virtual-account-webhook", [VirtualAccountsController::class, 'virtualAccountWebhook'])->name('business.virtual-account.virtualAccountWebhook');
    Route::any('callback/wallet/webhook/{userId}/{currency}', [CryptoWalletsController::class, 'walletWebhook'])->name('crypto.wallet.address.callback');
})->withoutMiddleware(VerifyCsrfToken::class);

Route::any('cron', [CronController::class, 'index'])->name('cron.index');




Route::get('bkp', function () {
    // $host = env('DB_HOST', 'localhost');
    // $username = env('DB_USERNAME', 'root');
    // $password = env('DB_PASSWORD', '');
    // $dbname = env('DB_DATABASE', 'your_database');

    // // Path to the SQL file in Laravel storage directory
    // $sqlFile = storage_path('mys.sql');  // Storage path in Laravel

    // // Check if the file exists
    // if (!File::exists($sqlFile)) {
    //     die("SQL file does not exist at " . $sqlFile);
    // }

    // // Create the connection
    // $conn = new mysqli($host, $username, $password, $dbname);

    // // Check connection
    // if ($conn->connect_error) {
    //     die("Connection failed: " . $conn->connect_error);
    // }

    // // Open the SQL backup file
    // $handle = fopen($sqlFile, "r");
    // if (!$handle) {
    //     die("Could not open SQL file.");
    // }

    // // Settings for chunking
    // $chunkSize = 500;  // Number of lines to read per chunk
    // $buffer = '';  // To store the SQL lines

    // // Read the file line by line
    // $lineCount = 0;
    // while (($line = fgets($handle)) !== false) {
    //     // Skip empty lines or comments
    //     $line = trim($line);
    //     if (empty($line) || substr($line, 0, 2) == '--') {
    //         continue;
    //     }

    //     // Append the current line to the buffer
    //     $buffer .= $line . "\n";
    //     $lineCount++;

    //     // When we reach the chunk size, execute the SQL and clear the buffer
    //     if ($lineCount >= $chunkSize) {
    //         if (!empty($buffer)) {
    //             // Execute the SQL chunk
    //             if ($conn->query($buffer) === FALSE) {
    //                 echo "Error: " . $conn->error . "\n";
    //             }
    //         }
    //         // Reset buffer and line count
    //         $buffer = '';
    //         $lineCount = 0;
    //     }
    // }

    // // Execute any remaining SQL in the buffer
    // if (!empty($buffer)) {
    //     if ($conn->query($buffer) === FALSE) {
    //         echo "Error: " . $conn->error . "\n";
    //     }
    // }

    // // Close the file and database connection
    // fclose($handle);
    // $conn->close();

    // echo "Database restore completed in chunks.";
});









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
