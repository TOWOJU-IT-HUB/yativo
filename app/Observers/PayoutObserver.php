<?php

namespace App\Observers;

use App\Models\Withdraw;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;

class PayoutObserver
{
    /**
     * Handle the Withdraw "updated" event.
     */
    public function updated(Withdraw $withdraw): void
    {
        // dispatch a webhook notification for payout notification
        $webhook_url = Webhook::whereUserId($withdraw->user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "payout.updated",
                "payload" => $withdraw->with('beneficiary')
            ])->dispatchSync();
        }
    }
}
