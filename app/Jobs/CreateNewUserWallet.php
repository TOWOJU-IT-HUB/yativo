<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateNewUserWallet
{
    use Dispatchable;

    public $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->user;
        $wallet = $user->createWallet([
            'name' => 'USD',
            'slug' => 'usd',
            'meta' => [
                "fullname" => "US Dollar",
                "symbol" => "$",
                "precision" => 2,
                "logo" => "https://catamphetamine.github.io/country-flag-icons/3x2/US.svg"
            ],
        ]);
    }
}
