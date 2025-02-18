<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\PayinMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Log;
use Modules\BinancePay\app\Http\Controllers\BinancePayController;
use Modules\BinancePay\app\Models\BinancePay;
use Modules\Flow\app\Http\Controllers\FlowController;

class CronDepositController extends Controller
{
    public function brla()
    {}

    public function floid()
    {
        //
    }

    public function vitawallet()
    {}

    public function onramp()
    {}

    public function transfi()
    {}

    public function bitso()
    {}
}