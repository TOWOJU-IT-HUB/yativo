<?php

namespace App\Observers;

use Modules\Customer\app\Models\Customer;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;

class CustomerObserver
{
    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        // dispatch a webhook notification
        $webhook_url = Webhook::whereUserId($customer->user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "customer.created",
                "payload" => $customer
            ])->dispatchSync();
        }
    } // 

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        // dispatch a webhook notification
        $webhook_url = Webhook::whereUserId($customer->user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "customer.updated",
                "payload" => $customer
            ])->dispatchSync();
        }
    }
} 
