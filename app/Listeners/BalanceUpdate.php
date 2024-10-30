<?php

namespace App\Listeners;

use App\Events\DebitCreditEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class BalanceUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DebitCreditEvent $event)
    {
        $customer = $event->customer;
        $balance = $customer->balance;

        if ($event->type === 'debit') {
            $balance->main_balance -= $event->amount;
        } elseif ($event->type === 'credit') {
            $balance->main_balance += $event->amount;
        }

        // Additional logic for ledger balance, currency conversion, etc.
        // $balance->ledger_balance += $event->amount;

        $balance->save();
    }
}
