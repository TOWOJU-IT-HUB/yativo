<?php
  
namespace App\Console\Commands;
  
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Http\Controllers\CronController;
use App\Http\Controllers\CronDepositController;
  
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