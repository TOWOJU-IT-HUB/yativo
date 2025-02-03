<?php

namespace Modules\Webhook\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Webhook\app\Models\Webhook;
use Illuminate\Support\Str;
use Spatie\WebhookServer\WebhookCall;

class WebhookController extends Controller
{
    public function index()
    {
        try {
            $webhooks = Webhook::where('user_id', auth()->id())->get();
            return get_success_response($webhooks);
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'url' => 'required|url',
                'events' => 'sometimes|string',
            ]);

            $webhook = Webhook::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                ],
                [
                    'url' => $request->url,
                    'secret' => Str::random(32),
                    'events' => $request->events ?? "general",
                ]
            );

            if ($webhook) {
                // send notification of successfully updating webhook
                $webhook_url = Webhook::whereUserId(auth()->id())->first();

                if ($webhook_url) {
                    WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                        "event.type" => "webhook_updated",
                        "payload" => $webhook
                    ])->dispatchSync();
                }
            }
            return get_success_response($webhook);
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'url' => 'required|url',
            ]);

            $webhook = Webhook::whereId($id)->first();

            if ($webhook) {
                $webhook->url = $request->url;

                if ($webhook->save()) {
                    return get_success_response($webhook);
                }
            }

            return get_error_response(['error' => 'Provided webhook not found!'], 400);
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function destroy(Webhook $webhook)
    {
        try {
            $webhook->delete();
            return get_success_response(['message' => 'Webhook deleted successfully']);
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function regenerateSecret(Webhook $webhook)
    {
        try {
            $webhook->update([
                'secret' => Str::random(32),
            ]);

            return get_success_response($webhook);
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
}
