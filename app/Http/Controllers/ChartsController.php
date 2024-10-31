<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class ChartsController extends Controller
{
    public function getWebhookStatusCounts(Request $request)
    {
        // Get the range from the request, defaulting to 'last_7_days'
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);

        $statusCounts = DB::table('webhook_logs')->whereUserId(auth()->id())
            ->select(DB::raw('DATE(created_at) as date'), 'status', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('date', 'status')
            ->orderBy('date', 'asc')
            ->get();

        // Initialize arrays to store daily counts for each status
        $dates = [];
        $successData = [];
        $failedData = [];

        // Populate the data arrays
        foreach ($statusCounts as $statusCount) {
            $date = Carbon::parse($statusCount->date)->format('Y-m-d');

            // Ensure each date appears in the results
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }

            if ($statusCount->status === 'success') {
                $successData[$date] = $statusCount->count;
            } elseif ($statusCount->status === 'failed') {
                $failedData[$date] = $statusCount->count;
            }
        }

        // Fill in missing dates with zeroes for each status
        $successData = array_map(fn($date) => $successData[$date] ?? 0, $dates);
        $failedData = array_map(fn($date) => $failedData[$date] ?? 0, $dates);

        // Prepare the response data
        $responseData = [
            'dates' => $dates,
            'success' => $successData,
            'failed' => $failedData,
        ];

        return response()->json($responseData);
    }
    
    public function countWebhookRequestMethodsPerDay(Request $request)
    {
        // Get the range from the request, defaulting to 'last_7_days'
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);

        $requestMethodCounts = DB::table('webhook_logs')->whereUserId(auth()->id())
            ->select(DB::raw('DATE(created_at) as date'), 'method', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('date', 'status')
            ->orderBy('date', 'asc')
            ->get();

        $data = [];
        foreach ($requestMethodCounts as $logCount) {
            $data[$logCount->date][$logCount->method] = $logCount->count;
        }

        return response()->json($data);
    }

    public function getApiLogCounts(Request $request)
    {
        // Get the range from the request, defaulting to 'last_7_days'
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);

        $logCounts = DB::table('api_logs')->whereUserId(auth()->id())
            ->select(DB::raw('DATE(created_at) as date'), 'method', DB::raw('COUNT(*) as count'), DB::raw('SUM(CASE WHEN response_status > 400 THEN 1 ELSE 0 END) as failed_count'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('date', 'method')
            ->orderBy('date', 'asc')
            ->get();

        // Initialize arrays to store daily counts for each method
        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        $successData = [];
        $failedData = [];

        // Populate the data arrays
        foreach ($methods as $method) {
            $successData[$method] = [];
            $failedData[$method] = [];
        }

        foreach ($logCounts as $logCount) {
            $date = Carbon::parse($logCount->date)->format('Y-m-d');
            $successCount = $logCount->count - $logCount->failed_count;

            // Initialize the date in success and failed data if not present
            if (!isset($successData[$logCount->method][$date])) {
                $successData[$logCount->method][$date] = 0;
            }
            if (!isset($failedData[$logCount->method][$date])) {
                $failedData[$logCount->method][$date] = 0;
            }

            // Update counts
            $successData[$logCount->method][$date] += $successCount;
            $failedData[$logCount->method][$date] += $logCount->failed_count;
        }

        // Fill in missing dates with zeroes for each method
        foreach ($methods as $method) {
            foreach ($logCounts as $logCount) {
                $date = Carbon::parse($logCount->date)->format('Y-m-d');
                if (!array_key_exists($date, $successData[$method])) {
                    $successData[$method][$date] = 0;
                }
                if (!array_key_exists($date, $failedData[$method])) {
                    $failedData[$method][$date] = 0;
                }
            }
        }

        // Prepare the response data
        $responseData = [
            'dates' => array_keys($successData['GET']),
            'success' => $successData,
            'failed' => $failedData,
        ];

        return response()->json($responseData);
    }

    public function countRequestMethodsPerDay(Request $request)
    {
        // Get the range from the request, defaulting to 'last_7_days'
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);

        $requestMethodCounts = DB::table('api_logs')->whereUserId(auth()->id())
            ->select(DB::raw('DATE(created_at) as date'), 'method', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('date', 'method')
            ->orderBy('date', 'asc')
            ->get();

        $data = [];
        foreach ($requestMethodCounts as $logCount) {
            $data[$logCount->date][$logCount->method] = $logCount->count;
        }

        return response()->json($data);
    }

    public function countSuccessVsFailed(Request $request)
    {
        // Get the range from the request, defaulting to 'last_7_days'
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDate($range);

        $successCount = DB::table('api_logs')->whereUserId(auth()->id())
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->where('response_status', 200) 
            ->orWhere('response_status', 201)
            ->count();

        $failedCount = DB::table('api_logs')
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->where('response_status', '>=', 400)
            ->count();

        return response()->json([
            'success' => $successCount,
            'failed' => $failedCount,
        ]);
    }

    private function getStartDate($range)
    {
        switch ($range) {
            case 'last_7_days':
                return Carbon::now()->subDays(7);
            case 'last_2_weeks':
                return Carbon::now()->subWeeks(2);
            case 'last_1_month':
                return Carbon::now()->subMonth();
            case 'last_3_months':
                return Carbon::now()->subMonths(3);
            case 'last_6_months':
                return Carbon::now()->subMonths(6);
            case 'last_1_year':
                return Carbon::now()->subYear();
            default:
                return Carbon::now()->subDays(7); 
        }
    }
}
