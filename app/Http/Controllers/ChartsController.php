<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class ChartsController extends Controller
{
    public function getWebhookStatusCounts(Request $request)
    {
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);
        
        // Determine grouping interval based on range
        $groupBy = $this->getGroupByInterval($range);

        $statusCounts = DB::table('webhook_logs')
            ->whereUserId(auth()->id())
            ->select(
                DB::raw("$groupBy as period"),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('period', 'status')
            ->orderBy('period', 'asc')
            ->get();

        // Initialize arrays to store counts
        $periods = [];
        $successData = [];
        $failedData = [];

        foreach ($statusCounts as $statusCount) {
            $period = $statusCount->period;

            if (!in_array($period, $periods)) {
                $periods[] = $period;
            }

            if ($statusCount->status === 'success') {
                $successData[$period] = $statusCount->count;
            } elseif ($statusCount->status === 'failed') {
                $failedData[$period] = $statusCount->count;
            }
        }

        // Fill in missing periods with zeros
        $successData = array_map(fn($period) => $successData[$period] ?? 0, $periods);
        $failedData = array_map(fn($period) => $failedData[$period] ?? 0, $periods);

        return response()->json([
            'periods' => $periods,
            'success' => $successData,
            'failed' => $failedData,
        ]);
    }

    public function countWebhookRequestMethodsPerDay(Request $request)
    {
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);
        $groupBy = $this->getGroupByInterval($range);

        $requestMethodCounts = DB::table('webhook_logs')
            ->whereUserId(auth()->id())
            ->select(
                DB::raw("$groupBy as period"),
                'method',
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('period', 'method')
            ->orderBy('period', 'asc')
            ->get();

        $data = [];
        foreach ($requestMethodCounts as $logCount) {
            $data[$logCount->period][$logCount->method] = $logCount->count;
        }

        return response()->json($data);
    }

    public function getApiLogCounts(Request $request)
    {
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);
        $groupBy = $this->getGroupByInterval($range);

        $logCounts = DB::table('api_logs')
            ->whereUserId(auth()->id())
            ->select(
                DB::raw("$groupBy as period"),
                'method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN response_status > 400 THEN 1 ELSE 0 END) as failed_count')
            )
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('period', 'method')
            ->orderBy('period', 'asc')
            ->get();

        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        $successData = [];
        $failedData = [];

        foreach ($methods as $method) {
            $successData[$method] = [];
            $failedData[$method] = [];
        }

        foreach ($logCounts as $logCount) {
            $period = $logCount->period;
            $successCount = $logCount->count - $logCount->failed_count;

            $successData[$logCount->method][$period] = $successCount;
            $failedData[$logCount->method][$period] = $logCount->failed_count;
        }

        return response()->json([
            'periods' => array_keys($successData['GET']),
            'success' => $successData,
            'failed' => $failedData,
        ]);
    }

    public function countRequestMethodsPerDay(Request $request)
    {
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);
        $groupBy = $this->getGroupByInterval($range);

        $requestMethodCounts = DB::table('api_logs')
            ->whereUserId(auth()->id())
            ->select(
                DB::raw("$groupBy as period"),
                'method',
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('period', 'method')
            ->orderBy('period', 'asc')
            ->get();

        $data = [];
        foreach ($requestMethodCounts as $logCount) {
            $data[$logCount->period][$logCount->method] = $logCount->count;
        }

        return response()->json($data);
    }

    public function countSuccessVsFailed(Request $request)
    {
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);
        $groupBy = $this->getGroupByInterval($range);

        $successCount = DB::table('api_logs')
            ->whereUserId(auth()->id())
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->where(function($query) {
                $query->where('response_status', 200)
                      ->orWhere('response_status', 201);
            })
            ->count();

        $failedCount = DB::table('api_logs')
            ->whereUserId(auth()->id())
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->where('response_status', '>=', 400)
            ->count();

        $dailyLogs = [];
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyLogs[$date] = [
                'success' => DB::table('api_logs')
                    ->whereUserId(auth()->id())
                    ->whereDate('created_at', $date)
                    ->whereIn('response_status', [200, 201])
                    ->count(),
                'failed' => DB::table('api_logs')
                    ->whereUserId(auth()->id())
                    ->whereDate('created_at', $date)
                    ->where('response_status', '>=', 400)
                    ->count(),
            ];
        }

        return response()->json([
            "total" => [
                'success' => $successCount,
                'failed' => $failedCount,
            ],
            "logs" => $dailyLogs,
        ]);
    }

    private function getStartDate($range)
    {
        switch ($range) {
            case 'last_7_days': return Carbon::now()->subDays(7);
            case 'last_2_weeks': return Carbon::now()->subWeeks(2);
            case 'last_1_month': return Carbon::now()->subMonth();
            case 'last_3_months': return Carbon::now()->subMonths(3);
            case 'last_6_months': return Carbon::now()->subMonths(6);
            case 'last_1_year': return Carbon::now()->subYear();
            default: return Carbon::now()->subDays(7); 
        }
    }

    private function getGroupByInterval($range)
    {
        switch ($range) {
            case 'last_7_days': return 'DATE(created_at)';
            case 'last_2_weeks': return 'WEEK(created_at)';
            case 'last_1_month': return 'WEEK(created_at)';
            case 'last_3_months': return 'WEEK(created_at)';
            case 'last_6_months': return 'MONTH(created_at)';
            case 'last_1_year': return 'MONTH(created_at)';
            default: return 'DATE(created_at)';
        }
    }
}
