<?php

namespace App\Events;

use App\Models\User;
use App\Notifications\WalletNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DebitCreditEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customer;
    public $amount;
    public $type; // 'debit' or 'credit'
    public $wallet; // ledger balance or main balance

    public function __construct(User $customer, $amount, $type, $wallet)
    {
        $this->customer = $customer;
        $this->amount = $amount;
        $this->type = $type;
        $this->wallet = $wallet;
        $customer->notifyNow(new WalletNotification($amount, $type, $wallet));
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('balance-update.'.active_user()),
        ];
    }

    public function broadcastWith()
    {
        return [
            'amount'    => $this->amount,
            'wallet'    => $this->wallet,
            'type'      => $this->type,
            'customer'  => $this->customer,
        ];
    }
}
