<?php 

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayoutMethodsController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:view payout methods')->only(['index', 'show']);
        // $this->middleware('can:create payout methods')->only(['create', 'store']);
        // $this->middleware('can:edit payout methods')->only(['edit', 'update']);
        // $this->middleware('can:delete payout methods')->only(['destroy']);
    }

    public function index()
    {
        $payoutMethods = PayoutMethods::paginate(15);
        return view('admin.payout_methods.index', compact('payoutMethods'));
    }

    public function create()
    {
        return view('admin.payout_methods.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'method_name' => 'required|string|max:255',
            'gateway' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'payment_mode' => 'required|string|max:255',
            'charges_type' => 'required|string|in:fixed,percentage',
            'fixed_charge' => 'nullable|numeric',
            'float_charge' => 'nullable|numeric',
            'estimated_delivery' => 'nullable|integer',
            'pro_fixed_charge' => 'nullable|numeric',
            'pro_float_charge' => 'nullable|numeric',
            'minimum_withdrawal' => 'nullable|numeric',
            'maximum_withdrawal' => 'nullable|numeric',
            'minimum_charge' => 'nullable|numeric',
            'maximum_charge' => 'nullable|numeric',
            'cutoff_hrs_start' => 'nullable',
            'cutoff_hrs_end' => 'nullable'
        ]);

        PayoutMethods::create($request->all());
        return redirect()->route('admin.payout-methods.index')->with('success', 'Payout method created successfully.');
    }

    public function show(PayoutMethods $payoutMethod)
    {
        return view('admin.payout_methods.show', compact('payoutMethod'));
    }

    public function edit(PayoutMethods $payoutMethod)
    {
        return view('admin.payout_methods.edit', compact('payoutMethod'));
    }

    public function update(Request $request, PayoutMethods $payoutMethod)
    {
        $request->validate([
            'method_name' => 'required|string|max:255',
            'gateway' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'payment_mode' => 'required|string|max:255',
            'charges_type' => 'required|string|in:fixed,percentage',
            'fixed_charge' => 'nullable|numeric',
            'float_charge' => 'nullable|numeric',
            'estimated_delivery' => 'nullable|integer',
            'pro_fixed_charge' => 'nullable|numeric',
            'pro_float_charge' => 'nullable|numeric',
            'minimum_withdrawal' => 'nullable|numeric',
            'maximum_withdrawal' => 'nullable|numeric',
            'minimum_charge' => 'nullable|numeric',
            'maximum_charge' => 'nullable|numeric',
            'cutoff_hrs_start' => 'nullable',
            'cutoff_hrs_end' => 'nullable'
        ]);

        $payoutMethod->update($request->all());
        return redirect()->route('admin.payout-methods.index')->with('success', 'Payout method updated successfully.');
    }

    public function destroy(PayoutMethods $payoutMethod)
    {
        $payoutMethod->delete();
        return redirect()->route('admin.payout-methods.index')->with('success', 'Payout method deleted successfully.');
    }
}
