<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficiaryFoemsController;
use App\Http\Controllers\BridgeController;
use App\Http\Controllers\Business\PlansController;
use App\Http\Controllers\Business\VirtualAccountsController;
use App\Http\Controllers\Business\WithdrawalController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ChartsController;
use App\Http\Controllers\CryptoWalletsController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\Google2faController;
use App\Http\Controllers\MagicLinkController;
use App\Http\Controllers\MantecaController;
use App\Http\Controllers\MiscController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PinVerificationController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\TransactionRecordController;
use App\Http\Controllers\UserMetaController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WhitelistedIPController;
use App\Http\Controllers\WithdrawalConntroller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Business\TeamController;
use App\Http\Controllers\FincraVirtualAccountController;
use App\Http\Middleware\IdempotencyMiddleware;
use Modules\Customer\app\Http\Controllers\DojahVerificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'v1/locations'], function () {
    Route::get('countries', [MiscController::class, 'countries'])->name('countries');
    Route::get('states', [MiscController::class, 'states'])->name('states');
    Route::get('states/{countryId}', [MiscController::class, 'states'])->name('state');
    Route::get('cities/{stateId}', [MiscController::class, 'city'])->name('cities');
    Route::get('jurisdictions', [MiscController::class, 'jurisdictions'])->name('jurisdictions');
});


Route::group(['prefix' => 'v1/auth'], function () {
    Route::get('verification-locations', [BridgeController::class, 'getCustomerRegistrationCountries']);
    Route::get('occupation-codes', [DojahVerificationController::class, 'occupationCodes']);

    Route::post('register', [AuthController::class, 'register']);
    Route::post('register/social', [AuthController::class, 'socialLogin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('send-verification-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('verify-otp', [MiscController::class, 'verifyOtp']);
    Route::post('validate-referrer-code', [AuthController::class, 'validateReferrer']);

    // magic authentication routes
    Route::post('login/magic', [MagicLinkController::class, 'sendMagicLink']);
    Route::post('login/magic-login', [MagicLinkController::class, 'loginWithMagicLink']);
    // Route::post('register/magic',               [MagicLinkController::class, 'sendMagicCode']);
    Route::post('register/complete/{token}', [MagicLinkController::class, 'completeRegistration']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password-with-otp', [AuthController::class, 'resetPasswordWithOtp']);
});


Route::middleware(['auth:api', 'kyc_check', IdempotencyMiddleware::class])->prefix('v1')->name('api.')->group(function () {
    Route::post('verify-document', [MiscController::class, 'validateDocument']);
    Route::get('generate-secret', [AuthController::class, 'generateAppSecret']);

    // Route::prefix('epay')->group(function () {
    //     Route::post('enroll-customer', [MantecaController::class, 'createUser']);
    //     Route::post('compliance', [MantecaController::class, 'uploadToS3']);
    //     Route::post('deposit', [MantecaController::class, 'createOrder']);
    //     Route::post('withdraw', [MantecaController::class, 'withdraw']);
    //     Route::get('customers', [MantecaController::class, 'mantecaCustomer']);
    // });

    Route::prefix('off-ramp-payout')->group(function () {
        Route::post('create-quote', [BitnobOffRampController::class, 'createQuote']);
        Route::post('initialize-payout', [BitnobOffRampController::class, 'initializePayout']);
        Route::post('finalize-payout', [BitnobOffRampController::class, 'finalizePayout']);
    });

    Route::prefix('crypto')->group(function () {
        Route::post('create-wallet', [CryptoWalletsController::class, 'createWallet']);
        Route::get('get-wallets', [CryptoWalletsController::class, 'getWallets']);
        Route::get('deposit-histories', [CryptoWalletsController::class, 'depositHistories']);
        Route::get('deposit-history/{depositId}', [CryptoWalletsController::class, 'depositHistory']);
        Route::delete('delete-wallet/{WalletId}', [CryptoWalletsController::class, 'deleteWallet']);
        Route::get('wallet/deposit/histories/{walletAddress}', [CryptoWalletsController::class, 'walletHistories']);
        Route::get('customer/wallets/{customerId}', [CryptoWalletsController::class, 'customerWallets']);
    });

    Route::post('pin/verify', [PinVerificationController::class, 'verifyPin']);
    Route::post('pin/update', [PinVerificationController::class, 'updatePin']);

    Route::group(['middleware' => 'google2fa'], function () {
        Route::post('generate-2fa-secret', [Google2faController::class, 'generateSecret']);
        Route::post('enable-2fa', [Google2faController::class, 'enable2fa']);
        Route::post('verify-2fa', [Google2faController::class, 'verify2fa']);
        Route::post('disable-2fa', [Google2faController::class, 'disable2fa']);
    });

    Route::post("exchange-rate", [MiscController::class, "exchangeRateFloat"]);
    Route::get('auth/refresh-token', [AuthController::class, 'refresh']);
    Route::post('generate-secret', [AuthController::class, 'generateAppSecret']);

    Route::get('profile', [AuthController::class, 'profile']);
    Route::delete('account/delete', [AuthController::class, 'deleteAccount']);
    Route::get('is-pin-set', [MiscController::class, 'isPinSet'])->name('isPinSet');
    Route::get("my-payin-methods", [MiscController::class, "getPayinMethods"]);

    Route::get("transaction/track", [TrackController::class, "track"]);


    Route::group([], function () {
        Route::put('profile', [AuthController::class, 'update']);
        Route::get('customer/kyc/{customerId}', [BridgeController::class, 'getCustomer']);
        Route::put('customer/kyc/update', [BridgeController::class, 'selfUpdateCustomer']);
        Route::get('user-meta', [UserMetaController::class, 'index']);
        Route::get('user-meta/{id}', [UserMetaController::class, 'show']);
        Route::post('user-meta', [UserMetaController::class, 'store']);
        Route::put('user-meta/{id}', [UserMetaController::class, 'update']);
        Route::delete('user-meta/{id}', [UserMetaController::class, 'destroy']);
        Route::post('storage/upload', [UserMetaController::class, 'upload'])->name('misc.upload')->withoutMiddleware('kyc_check');
        Route::get('storage/get/{doc}', [UserMetaController::class, 'retriveUpload'])->name('misc.get');
    })->middleware('kyc_check');


    Route::prefix('beneficiary/form')->group(function () {
        Route::post('create', [BeneficiaryFoemsController::class, 'store']);
        Route::get('all', [BeneficiaryFoemsController::class, 'get']);
        Route::get('show/{currency}', [BeneficiaryFoemsController::class, 'show']);
    })->middleware('kyc_check');


    Route::group(['prefix' => 'wallet'], function () {
        Route::group(['prefix' => 'deposits'], function () {
            Route::get('/', [DepositController::class, 'index']);
            Route::post('new', [DepositController::class, 'store']);
        });
        Route::post('yativo-transfer', [WalletController::class, 'yativoTransfer'])->middleware('chargeWallet')->name('wallet.yativotransfer');
        Route::get('payouts', [WalletController::class, 'index']);
        Route::post('payout', [WithdrawalConntroller::class, 'store'])->middleware('chargeWallet')->name('wallet.payout');
        Route::get('payout/{id}', [WithdrawalConntroller::class, 'show']);

        Route::get('balance', [WalletController::class, 'balance']);
        Route::get('balance/{total}', [WalletController::class, 'balance']);
        Route::post('create', [WalletController::class, 'addNewWallet']);
    })->middleware('kyc_check');

    Route::group(['prefix' => 'ip'], function () {
        Route::get('/', [WhitelistedIPController::class, 'index']);
        Route::post('/', [WhitelistedIPController::class, 'store']);
        Route::get('{whitelistedIP}', [WhitelistedIPController::class, 'show']);
        Route::put('{whitelistedIP}', [WhitelistedIPController::class, 'update']);
        Route::delete('{whitelistedIP}', [WhitelistedIPController::class, 'destroy']);
    })->middleware('kyc_check');

    Route::group(['prefix' => 'payment-methods'], function () {
        Route::get("payin", [DepositController::class, 'payinMethods']);
        Route::get("payin/countries", [DepositController::class, 'payinMethodsCountries']);
        Route::get("payin/currency", [DepositController::class, 'getPayinCurrencies']);
        Route::get("payout", [WithdrawalConntroller::class, 'getPayoutMethods']);
        Route::get("payout/countries", [WithdrawalConntroller::class, 'payoutMethodsCountries']);
    })->middleware('kyc_check');

    Route::prefix('payout')->group(function () {
        Route::post('simple', [WithdrawalController::class, 'singlePayout'])->middleware('chargeWallet');
        Route::post('batch', [WithdrawalController::class, 'bulkPayout'])->middleware('chargeWallet');
        Route::get('get', [WithdrawalController::class, 'getPayouts']);
        Route::get('fetch/{payout_id}', [WithdrawalController::class, 'getPayout']);
    });

    Route::prefix('otp')->group(function () {
        Route::post('send', [OtpController::class, 'send']);
        Route::post('verify', [OtpController::class, 'verify']);
    });

    Route::prefix('business')->group(function () {
        Route::get('configs', [BusinessController::class, 'preference'])->name('business.preference');
        Route::put('configs', [BusinessController::class, 'updatePreference'])->name('business.preference.update');
        Route::post('notify-ubo', [BusinessController::class, 'sendEmailNotification'])->name('business.notify.ubo.post');
        Route::get('notify-ubo', [BusinessController::class, 'sendEmailNotification'])->name('business.notify.ubo');
        Route::get('ubos', [BusinessController::class, 'uboList'])->name('business.ubo.list');
        Route::post('/', [BusinessController::class, 'store'])->name('business.store');
        Route::put('/', [BusinessController::class, 'update'])->name('business.update');
    });

    Route::prefix('business')->group(function () {

        Route::get('transactions/all', [TransactionRecordController::class, 'index']);
        Route::get('transaction/show/{transactionId}', [TransactionRecordController::class, 'show']);
        Route::get('transaction/by-currency', [TransactionRecordController::class, 'byCurrency']);
        Route::get('chart-data', [TransactionRecordController::class, 'getChartData']);

        Route::get('details', [BusinessController::class, 'show'])->name('business.show');

        Route::prefix('virtual-account')->group(function () {
            Route::get("/", [VirtualAccountsController::class, 'index'])->name('business.virtual-account.index');
            Route::get("show/{externalId}", [VirtualAccountsController::class, 'show'])->name('business.virtual-account.show');
            Route::post("create", [VirtualAccountsController::class, 'create'])->name('business.virtual-account.create');
            Route::get("get_account_details/{account_id}", [VirtualAccountsController::class, 'get_account_details']);
            Route::post("bulk-account-creation", [VirtualAccountsController::class, 'bulk_account_creation'])->name('business.virtual-account.bulk_account_creation');
            Route::delete("delete-virtual-account/{account_id}", [VirtualAccountsController::class, 'delete_virtual_account'])->name('business.virtual-account.delete_virtual_account');
            Route::put("enable_disable_virtual_account", [VirtualAccountsController::class, 'enable_disable_virtual_account'])->name('business.virtual-account.enable_disable_virtual_account');
            Route::post("refundPayin/{externalId}", [VirtualAccountsController::class, 'refundPayin'])->name('business.virtual-account.refundPayin');
            Route::post("history/{accountNumber}", [VirtualAccountsController::class, 'get_history'])->name('business.virtual-account.payin.history');

            Route::get("customer/accounts/{customerId}", [VirtualAccountsController::class, 'customerVirtualAccounts'])->name('business.virtual-account.customer.accounts');

            Route::get("{account_id}/transactions", [VirtualAccountsController::class, 'get_account_details'])->name('business.virtual-account.get_account_details');


            // add fincra virtual account generation and management for USD and EUR accounts

            Route::post('va/create', [FincraVirtualAccountController::class, 'createVirtualAccount']);
            Route::get('va/{virtualAccountId}', [FincraVirtualAccountController::class, 'getVirtualAccount']);
            Route::get('account-deposits/{businessId}/{virtualAccountId}', [FincraVirtualAccountController::class, 'getAccountDepositHistory']);
            Route::get('multicurrency-accounts', [FincraVirtualAccountController::class, 'listMulticurrencyAccounts']);

        });

        Route::prefix('plans')->group(function () {
            Route::get('current', [PlansController::class, 'index']);
            Route::get('all', [PlansController::class, 'plans']);
            Route::post('subscribe/{plan_id}', [PlansController::class, 'subscribe'])->middleware('chargeWallet');
            Route::put('upgrade/{plan_id}', [PlansController::class, 'upgrade']);
        });

        Route::prefix('logs')->group(function () {
            Route::get('all', [EventsController::class, 'index']);
            Route::get('show/{eventId}', [EventsController::class, 'show']);
        });
        Route::prefix('events')->group(function () {
            Route::get('all', [EventsController::class, 'getWebhookLogs']);
            Route::get('show/{eventId}', [EventsController::class, 'showWebhookLog']);
        });

    })->middleware('kyc_check');


    Route::prefix('teams')->group(function () {
        Route::get('{teamId}/owner', [TeamController::class, 'getOwner']);
        Route::get('{teamId}/users', [TeamController::class, 'getUsers']);
        Route::get('{teamId}/all-users', [TeamController::class, 'getAllUsers']);
        Route::get('{teamId}/has-user/{userId}', [TeamController::class, 'hasUser']);
        Route::get('{teamId}/abilities', [TeamController::class, 'getAbilities']);
        Route::get('{teamId}/roles', [TeamController::class, 'getRoles']);
        Route::get('{teamId}/role/{roleId}', [TeamController::class, 'findRole']);
        Route::get('{teamId}/user-role/{userId}', [TeamController::class, 'getUserRole']);
        Route::post('{teamId}/role', [TeamController::class, 'addRole']);
        Route::put('{teamId}/role/{roleName}', [TeamController::class, 'updateRole']);
        Route::delete('{teamId}/role/{roleName}', [TeamController::class, 'deleteRole']);
        Route::get('{teamId}/groups', [TeamController::class, 'getGroups']);
        Route::get('{teamId}/group/{groupCode}', [TeamController::class, 'getGroup']);
        Route::post('{teamId}/group', [TeamController::class, 'addGroup']);
        Route::delete('{teamId}/group/{groupCode}', [TeamController::class, 'deleteGroup']);
        Route::post('{teamId}/has-user-with-email', [TeamController::class, 'hasUserWithEmail']);
        Route::get('{teamId}/user-has-permission/{userId}/{permission}', [TeamController::class, 'userHasPermission']);
        Route::delete('{teamId}/user/{userId}', [TeamController::class, 'deleteUser']);
        Route::get('{teamId}/invitations', [TeamController::class, 'getInvitations']);
        Route::post('authenticate', [TeamController::class, 'authenticateStaff']);
        Route::post('{teamId}/assign-role/{userId}/{roleName}', [TeamController::class, 'assignRole']);
        Route::post('{teamId}/revoke-role/{userId}/{roleName}', [TeamController::class, 'revokeRole']);
        Route::post('{teamId}/give-permission/{userId}/{permission}', [TeamController::class, 'givePermissionTo']);
        Route::post('{teamId}/revoke-permission/{userId}/{permission}', [TeamController::class, 'revokePermissionTo']);
    });


    Route::prefix('charts')->group(function () {
        Route::get('api-logs/request-methods', [ChartsController::class, 'countRequestMethodsPerDay']);
        Route::get('api-logs/success-failed', [ChartsController::class, 'countSuccessVsFailed']);
        Route::get('webhook-logs/request-methods', [ChartsController::class, 'countWebhookRequestMethodsPerDay']);
        Route::get('webhook-logs/success-failed', [ChartsController::class, 'countWebhookSuccessVsFailed']);
    });
});
