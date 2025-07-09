<?php
  
namespace App\Console\Commands;
  
use App\Models\CheckoutModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CronDepositController;
use Carbon\Carbon;
  
class DepositCronCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deposit:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Running deposit cronjob';
  
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $checkouts = CheckoutModel::where('status', 'pending')->get();
        foreach ($checkouts as $checkout) {
            $createdAt = Carbon::parse($checkout->created_at);
            $expiresIn = $createdAt->addMinutes($checkout->expiration_time);

            if (now()->greaterThanOrEqualTo($expiresIn)) {
                // Expired
                // e.g. mark as failed or remove
                $checkout->status = 'expired';
                $checkout->save();
            }
        } 
        

        $payout = new CronController();
        $payout->checkForBridgeVirtualAccountDeposits();
        $payout->bitso();

        $deposit = new CronDepositController();
        // info("Cron Job running at ". now());

        $deposit->brla();
        $deposit->getFloidStatus();
        $deposit->vitawallet();
        $deposit->transfi();
        $deposit->onramp();
        $deposit->bitso();


        // handle USD virtual account deposits
    }
}