<?php

namespace App\Http\Controllers;

use App\Models\TransactionRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransactionRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $records = TransactionRecord::with(['beneficiary', 'user'])->whereUserId(auth()->id())->paginate(per_page());
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
        // Default currency filter (optional)
        $currency = $request->input('currency', null);

        // Date range filter
        $dateRange = $request->input('range', 'last_7_days'); // Default is 'last 7 days'
        $startDate = $this->getStartDateForRange($dateRange);
        $endDate = Carbon::now();

        // Query the TransactionRecords model
        $query = TransactionRecord::where('user_id', auth()->id());

        // Filter by currency if provided
        if ($currency) {
            $query->where('transaction_currency', $currency);
        }

        // Filter by date range
        $query->whereBetween('created_at', [$startDate, $endDate]);

        // Get the data
        $transactionRecords = $query->get();

        // Group the transactions by date for Chart.js
        $chartData = $this->formatForChartJs($transactionRecords);

        return response()->json($chartData);
    }

    // Helper method to determine the start date based on the selected range
    private function getStartDateForRange($range)
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
                return Carbon::now()->subDays(7); // Default: last 7 days
        }
    }

    // Helper method to format the data for Chart.js
    private function formatForChartJs($transactionRecords)
    {
        $chartData = [
            'labels' => [],  // Holds the dates
            'data' => [],    // Holds the total amounts
        ];

        // Group the transaction records by date and sum the amounts
        $grouped = $transactionRecords->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        });

        foreach ($grouped as $date => $transactions) {
            $chartData['labels'][] = $date;
            $chartData['data'][] = $transactions->sum('amount'); // Assuming 'amount' is the field for the transaction value
        }

        return $chartData;
    }
}
