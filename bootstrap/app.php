<?php

use App\Http\Middleware\Admin\Require2FA;
use App\Http\Middleware\ChargeWalletMiddleware;
use App\Http\Middleware\CustomerKycMiddleware;
use App\Http\Middleware\CustomerVirtualAccountMiddleware;
use App\Http\Middleware\CustomerVirtualCardCharges;
use App\Http\Middleware\CustomerVirtualCardMiddleware;
use App\Http\Middleware\EnterprisePlanMiddleware;
use App\Http\Middleware\Google2faMiddleware;
use App\Http\Middleware\JsonRequestMiddleware;
use App\Http\Middleware\KycStatusMiddleware;
use App\Http\Middleware\LogRequestResponse;
use App\Http\Middleware\SanitizeHeadersMiddleware;
use App\Http\Middleware\ScalePlanMiddleware;
use App\Http\Middleware\WhitelistIPMiddleware;
use Aws\TrustedAdvisor\TrustedAdvisorClient;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/admin.php',
        ],
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(
            prepend: [
                JsonRequestMiddleware::class,
                // WhitelistIPMiddleware::class,
                CustomerKycMiddleware::class,
                LogRequestResponse::class,
                SanitizeHeadersMiddleware::class,
            ]
        );

        $middleware->alias([
            'google2fa' => Google2faMiddleware::class,
            'kyc_check' => KycStatusMiddleware::class,
            'can_create_vc' => CustomerVirtualCardMiddleware::class,
            'can_create_va' => CustomerVirtualAccountMiddleware::class,
            'vc_charge' => CustomerVirtualCardCharges::class,
            'chargeWallet' => ChargeWalletMiddleware::class,
            'scale' => ScalePlanMiddleware::class,
            'enterprise' => EnterprisePlanMiddleware::class,
            'admin.2fa' => Require2FA::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            "callback/*",
            "webhook/*",
            "volet/payin/success",
            "redirect/*"
        ]);
        $middleware->statefulApi();
    })->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(
            fn(AuthenticationException $exception, $request) => get_error_response(
                'Unauthenticated',
                401
            )
        );
    })->create();
