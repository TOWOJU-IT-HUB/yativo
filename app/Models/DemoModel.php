<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemoModel extends Model
{
    public function response($payload)
    {
        $data = [
            "deposit_url" => "https://smtp.yativo.com/process-payin/da653fbf-4d55-4343-a569-5e606edc96f9/paynow",
            "deposit_data" => [
                "currency" => "CLP",
                "deposit_currency" => "usd",
                "amount" => $payload["amount"],
                "gateway" => 51,
                "receive_amount" => 9029,
                "customer_id" => null,
                "id" => "da653fbf-4d55-4343-a569-5e606edc96f9",
                "updated_at" => "2025-05-27T08:24:48.000000Z",
                "created_at" => "2025-05-27T08:24:48.000000Z"
            ],
            "payment_info" => [
                "send_amount" => $payload["amount"]." CLP",
                "receive_amount" => "9029 USD",
                "exchange_rate" => "1 USD = 950.742 CLP",
                "transaction_fee" => "970.74 CLP",
                "payment_method" => "Bank Transfer - Khipu",
                "estimate_delivery_time" => "1 Hour(s)",
                "total_amount_due" => $payload["amount"]." CLP",
                "calc" => [
                    "deposit_amount" => $payload["amount"],
                    "exchange_rate" => 950.742,
                    "percentage_fee" => 20,
                    "fixed_fee_in_quote" => 950.74,
                    "total_fees" => 970.74,
                    "credited_amount" => 9029.26
                ]
            ]
        ];

        return $data;
    }


    public function sendWebhook($user_id)
    {
        $deposit = Deposit::latest()->makeHidden(['user_id', 'raw_data']);
        // dispatch a webhook notification
        $webhook_url = Webhook::whereUserId($user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "deposit.created",
                "payload" => $deposit
            ])->dispatchSync();
        }


        // dispatch a webhook notification
        $webhook_url = Webhook::whereUserId($user_id)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                "event.type" => "deposit.created",
                "payload" => $deposit
            ])->dispatchSync();
        }
    }
}
