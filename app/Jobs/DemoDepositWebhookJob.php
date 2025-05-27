<?php

namespace App\Jobs;

use App\Models\Business;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ShuftiPro\app\Services\ShuftiProServices;

class DemoDepositWebhookJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $deposit;

    public function __construct($deposit)
    {
        $this->deposit = $deposit;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $deposit = $this->deposit;

        if($deposit->amount < 20000) {
            // dispatch a successful deposit webhook
        } else {
            // dispatch a failed transaction webhook
        }
    }
}
