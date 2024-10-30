<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BulkPayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payouts, $batchId;
    /**
     * Create a new job instance.
     */
    public function __construct($payouts, $batchId)
    {
        $this->payouts = $payouts;
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // process payouts by iterating through the array.
        $batchId = $this->batchId;
        $withdrawals = [];

        foreach($this->payouts as $payouts) {
            $withdrawals[] = $this->processJob();
        }
    }

    private function processJob()
    {
        //
    }
}
