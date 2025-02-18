<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\payoutMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class payoutMethodsController extends Controller
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
        $query = payoutMethods::query();

        if (request('currency')) {
            $query->where('currency', request('currency'));
        }

        if (request('gateway')) {
            $query->where('gateway', 'like', '%' . request('gateway') . '%');
        }

        if (request('method_name')) {
            $query->where('method_name', 'like', '%' . request('method_name') . '%');
        }

        if (request('country')) {
            $query->where('country', 'like', '%' . request('country') . '%');
        }

        if (request('show_deleted')) {
            $query->withTrashed();
        }

        $payoutMethods = $query->paginate(per_page())->withQueryString();
        return view('admin.payout_methods.index', compact('payoutMethods'));
    }

    public function create()
    {
        return view('admin.payout_methods.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'method_name' => 'required|string|max:255',
            'gateway' => 'required|string|max:255',
            'country' => 'sometimes|string|max:255',
            'currency' => 'required|string|max:3',
            'payment_mode' => 'required|string|max:255',
            'charges_type' => 'required|string|in:fixed,percentage,combined',
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
            'exchange_rate_float' => 'sometimes',
            'base_currency' => 'sometimes'
        ]);

        if (empty($request->country)) {
            $validatedData['country'] = 'global';
        }
        payoutMethods::create($request->all());
        return redirect()->route('admin.payout-methods.index')->with('success', 'Payout method created successfully.');
    }

    public function show(payoutMethods $payoutMethod)
    {
        return view('admin.payout_methods.show', compact('payoutMethod'));
    }

    public function edit(payoutMethods $payoutMethod)
    {
        return view('admin.payout_methods.edit', compact('payoutMethod'));
    }

    public function update(Request $request, payoutMethods $payoutMethod)
    {
        $request->validate([
            'method_name' => 'required|string|max:255',
            'gateway' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'payment_mode' => 'required|string|max:255',
            'charges_type' => 'required|string|in:fixed,percentage,combined',
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
            'cutoff_hrs_end' => 'nullable',
            'exchange_rate_float' => 'sometimes',
            'base_currency' => 'sometimes',
        ]);

        $payoutMethod->update($request->all());
        return redirect()->route('admin.payout-methods.index')->with('success', 'Payout method updated successfully.');
    }

    public function destroy(payoutMethods $payoutMethod)
    {
        $payoutMethod->delete();
        return redirect()->route('admin.payout-methods.index')->with('success', 'Payout method deleted successfully.');
    }
}
