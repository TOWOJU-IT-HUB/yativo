<?php

namespace App\Listeners;

use App\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogFailedWebhook
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
        // Log::info("Webhook Event has the following data", (array)$event);
        WebhookLog::create([
            'user_id' => $event->meta['_uid'] ?? null,
            'url' => $event->webhookUrl,
            'payload' => (array)$event->payload,
            'status' => 'failed',
            'status_code' => $event->response ? $event->response->getStatusCode() : null,
            'response_body' => $event->response ? $event->response->getBody()->getContents() : 'No response',
        ]);
    }
}
