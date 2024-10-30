<?php

namespace Modules\SendMoney\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SaveSendMoneyResponse implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $quoteId, $user_request, $apiResponse;

    /**
     * Create a new job instance.
     */
    public function __construct($quoteId, $user_request, $apiResponse)
    {
        $this->quoteId = $quoteId;
        $this->user_request = $user_request;
        $this->apiResponse = $apiResponse;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            updateSendMoneyRawData(
                $this->quoteId, 
                [
                    'user_request' =>  result($this->user_request),
                    'gateway_response' => result($this->apiResponse)
                ]
            );
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
        }
    }
}
