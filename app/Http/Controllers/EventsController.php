<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class EventsController extends Controller
{
    public function index()
    {
        try {
            $activity = ApiLog::where('user_id', auth()->id())
                ->when(request('status'), function($query) {
                    return $query->where('response_status', request('status'));
                })
                ->when(request('method'), function($query) {
                    return $query->where('method', request('method'));
                })
                ->latest()->limit(300)
                ->paginate(per_page());
            return paginate_yativo($activity);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function show($eventId)
    {
        try {
            $activity = ApiLog::where('user_id', auth()->id())->whereId($eventId)->latest()->first();

            if(!$activity) {
                return get_error_response(['error' => 'Event not found'], 404);
            }
    
            return get_success_response($activity);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function getWebhookLogs(Request $request)
    {
        try {
            $activity = WebhookLog::where('user_id', auth()->id())->latest()->paginate(per_page());

            return paginate_yativo($activity);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function showWebhookLog($eventId)
    {
        try {
            $activity = WebhookLog::whereId($eventId)->where('user_id', auth()->id())->latest()->first();
            return get_success_response($activity);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
    
}
