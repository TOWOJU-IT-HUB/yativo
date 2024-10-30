<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\CoinPayments\app\Http\Controllers\CoinPaymentsController;

class CreateUSDWalletEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $amount, $wallet, $type, $customer;
    
    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        $user = User::find(auth()->user()->id);
        $coin = new CoinPaymentsController;
        $generate = $coin->generateAddress($user->id);
        if(!$user->hasWallet('usd')) {
            // Add USD wallet if it doesn't exist
            $wallet = $user->createWallet([
                'name' => 'USD',
                'slug' => 'usd',
                'decimal_places' => 2,
                'meta' => [
                    "fullname" => "US Dollar",
                    "symbol" => "$",
                    "precision" => 2,
                    "logo" => "https://catamphetamine.github.io/country-flag-icons/3x2/US.svg"
                ],
            ]);
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('new-wallet-created.'.active_user()),
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
