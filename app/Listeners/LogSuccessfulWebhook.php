<?php

namespace App\Listeners;

use App\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogSuccessfulWebhook
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
    public function handle(object $event): void
    {
        WebhookLog::create([
            'user_id' => $event->meta['_uid'] ?? null,
            'url' => $event->webhookUrl,
            'payload' => (array)$event->payload,
            'status' => 'success',
            'status_code' => $event->response->getStatusCode(),
            'response_body' => $event->response->getBody()->getContents(),
        ]);
    }
}
