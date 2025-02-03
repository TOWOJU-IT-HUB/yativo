<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // Fetch the activity logs, including relations, and apply search/sorting
        $logs = Activity::query()
            ->with(['subject', 'causer'])
            ->when($request->input('search'), function (Builder $query, $search) {
                $query->where('log_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('subject', function (Builder $query) use ($search) {
                        $query->where('transaction_type', 'like', "%{$search}%");
                    })
                    ->orWhereHas('causer', function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            })
            ->orderBy($request->input('sort', 'log_name'))
            ->paginate(per_page())->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }

    public function show(Activity $activityLog)
    {
        // Show a specific activity log
        return view('admin.activity-logs.show', compact('activityLog'));
    }

    public function destroy(Activity $activityLog)
    {
        // Delete a specific activity log
        $activityLog->delete();

        return redirect()->route('admin.activity-logs.index')->with('success', 'Log deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        // Bulk delete activity logs
        Activity::whereIn('id', $request->input('ids', []))->delete();

        return redirect()->route('admin.activity-logs.index')->with('success', 'Selected logs deleted successfully.');
    }
}
