<?php

namespace App\Observers;

use App\Models\Withdraw;

class PayoutObserver
{
    /**
     * Handle the Withdraw "updated" event.
     */
    public function updated(Withdraw $withdraw): void
    {
        // dispatch a webhook notification for payout notification
        $webhook_url = Webhook::whereUserId($deposit->user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "deposit.success",
                "payload" => $deposit
            ])->dispatchSync();
        }
    }
}
