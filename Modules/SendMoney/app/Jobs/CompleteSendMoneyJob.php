<?php

namespace Modules\SendMoney\app\Jobs;

use App\Services\PayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\SendMoney\app\Models\SendQuote;

class CompleteSendMoneyJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $quoteId;
    /**
     * Create a new job instance.
     */
    public function __construct($quoteId)
    {
        $this->quoteId = $quoteId;
    }

    /**
     * Execute the job. 
     */
    public function handle(): void 
    {
        try {
            $quoteId = $this->quoteId;
			
            $send_money = SendMoney::whereQuoteId($quoteId)->first();
			$send_money->status = 'processing_payout';
			$send_money->save();

			$quote = SendQuote::whereId($quoteId)->first();
			$quote->status = 'processing_payout';
			$quote->save();

			// Inititate payout proccess
			$payout  = new PayoutService();
			$init_payout = $payout->makePayment($quoteId, $quote->receive_gateway);
            \Log::info($init_payout);
            return $init_payout;
		} catch (\Throwable $th) {
			error_log(json_encode(['error_message' => $th->getMessage()]));
		}
    }
}

