<?php

namespace App\Observers;

use App\Models\Deposit;

class DepositObserver
{
    /**
     * Handle the Deposit "updated" event.
     */
    public function updated(Deposit $deposit): void
    {
        // dispatch a webhook notification
        $webhook_url = Webhook::whereUserId($deposit->user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "deposit.success",
                "payload" => $deposit
            ])->dispatchSync();
        }
    }
}
