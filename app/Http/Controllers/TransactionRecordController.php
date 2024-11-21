<?php

namespace App\Http\Controllers;

use App\Models\TransactionRecord;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class TransactionRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $status = request('status');
            $startDate = request('start_date');
            $endDate = request('end_date');
            $amount = request('amount');

            $records = TransactionRecord::with(['beneficiary', 'user'])
                ->whereUserId(auth()->id())
                ->when($status, function($query) use ($status) {
                    return $query->where('transaction_status', $status);
                })
                ->when($startDate, function($query) use ($startDate) {
                    return $query->whereDate('created_at', '>=', $startDate);
                })
                ->when($endDate, function($query) use ($endDate) {
                    return $query->whereDate('created_at', '<=', $endDate);
                })
                ->when($amount, function($query) use ($amount) {
                    return $query->where('transaction_amount', $amount);
                })
                ->latest()
                ->paginate(per_page());
                
            return paginate_yativo($records);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }    }

    /**
     * Display a listing of the resource.
     */
    public function byCurrency(Request $request)
    {
        try {
            $currency = $request->input('currency', 'usd');
            $records = TransactionRecord::with(['beneficiary', 'user', 'customer'])
                ->where('base_currency', $currency)
                ->orWhere('secondary_currency', $currency)
                ->whereUserId(auth()->id())
                ->latest()
                ->paginate(per_page());

            return paginate_yativo($records);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($recordId)
    {
        try {
            $record = TransactionRecord::with(['beneficiary', 'user', 'payinMethod', 'payoutMethod'])
                ->whereId($recordId)
                ->whereUserId(auth()->id())
                ->first();

            if (!$record) {
                return get_error_response(['error' => 'Transaction with the provided ID not found!'], 404);
            }

            return get_success_response($record);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }

    public function getChartData(Request $request)
    {
        $currency = $request->input('currency', null);
        $range = $request->input('range', 'last_7_days');
        $startDate = $this->getStartDateForRange($range)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Determine the grouping interval based on the selected range
        $groupByInterval = $this->getGroupByInterval($range);

        // Query the transaction records
        $query = TransactionRecord::where('user_id', auth()->id())
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($currency) {
            $query->where('transaction_currency', $currency);
        }

        $transactionRecords = $query->get();

        // Format data for Chart.js
        $chartData = $this->formatForChartJs($transactionRecords, $range, $groupByInterval, $startDate, $endDate);

        return response()->json($chartData);
    }

    // Helper method to determine the start date based on the selected range
    private function getStartDateForRange($range)
    {
        switch ($range) {
            case 'last_7_days':
                return Carbon::now()->subDays(6);
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
                return Carbon::now()->subDays(6);
        }
    }

    // Helper method to determine the grouping interval based on the range
    private function getGroupByInterval($range)
    {
        switch ($range) {
            case 'last_2_weeks':
                return '2_days'; // 2 days interval
            case 'last_1_month':
                return '3_days'; // 3 days interval
            case 'last_3_months':
                return 'week'; // Weekly interval
            case 'last_6_months':
                return '2_weeks'; // 2 weeks interval
            case 'last_1_year':
                return 'month'; // Monthly interval
            default:
                return 'day'; // Default to daily
        }
    }

    // Helper method to format data for Chart.js based on the range and custom interval
    private function formatForChartJs($transactionRecords, $range, $interval, $startDate, $endDate)
    {
        $chartData = [
            'labels' => [],
            'data' => []
        ];

        $grouped = $this->groupByCustomInterval($transactionRecords, $interval, $startDate, $endDate);

        // Ensure all intervals are included even if no transactions exist for them
        foreach ($grouped as $period => $transactions) {
            $chartData['labels'][] = $period;
            $chartData['data'][] = $transactions->sum('transaction_amount');
        }

        return $chartData;
    }

    // Helper method to group transaction records by a custom interval
    private function groupByCustomInterval($transactionRecords, $interval, $startDate, $endDate)
    {
        // Initialize the empty collection for the intervals
        $intervals = collect();

        // Determine the intervals for the given range
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            // Format the current interval (date, week, month)
            switch ($interval) {
                case '2_days':
                    $intervalKey = $currentDate->startOfDay()->subDays($currentDate->dayOfWeek % 2)->format('Y-m-d');
                    break;
                case '3_days':
                    $intervalKey = $currentDate->startOfDay()->subDays($currentDate->dayOfYear % 3)->format('Y-m-d');
                    break;
                case 'week':
                    $intervalKey = $currentDate->startOfWeek()->format('Y-W');
                    break;
                case '2_weeks':
                    $intervalKey = $currentDate->startOfWeek()->subDays($currentDate->dayOfWeek % 14)->format('Y-m-d');
                    break;
                case 'month':
                    $intervalKey = $currentDate->format('Y-m');
                    break;
                default:
                    $intervalKey = $currentDate->format('Y-m-d');
                    break;
            }

            // Add the interval to the intervals collection if it doesn't exist yet
            if (!$intervals->has($intervalKey)) {
                $intervals[$intervalKey] = collect();
            }

            $currentDate->add($this->getIntervalAddDuration($interval));
        }

        // Now, group the actual transactions by these intervals
        $grouped = $transactionRecords->groupBy(function ($item) use ($interval) {
            $date = Carbon::parse($item->created_at);
            switch ($interval) {
                case '2_days':
                    return $date->startOfDay()->subDays($date->dayOfWeek % 2)->format('Y-m-d');
                case '3_days':
                    return $date->startOfDay()->subDays($date->dayOfYear % 3)->format('Y-m-d');
                case 'week':
                    return $date->startOfWeek()->format('Y-W');
                case '2_weeks':
                    return $date->startOfWeek()->subDays($date->dayOfWeek % 14)->format('Y-m-d');
                case 'month':
                    return $date->format('Y-m');
                default:
                    return $date->format('Y-m-d');
            }
        });

        // Combine grouped transactions with empty intervals, adding 0 if no transactions in that interval
        foreach ($intervals as $intervalKey => $_) {
            if (!isset($grouped[$intervalKey])) {
                $grouped[$intervalKey] = collect();
            }
        }

        return $grouped;
    }

    // Helper method to get the interval duration for date manipulation
    private function getIntervalAddDuration($interval)
    {
        switch ($interval) {
            case '2_days':
                return \Carbon\CarbonInterval::days(2);
            case '3_days':
                return \Carbon\CarbonInterval::days(3);
            case 'week':
                return \Carbon\CarbonInterval::weeks(1);
            case '2_weeks':
                return \Carbon\CarbonInterval::weeks(2);
            case 'month':
                return \Carbon\CarbonInterval::months(1);
            default:
                return \Carbon\CarbonInterval::days(1);
        }
    }
}
