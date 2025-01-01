<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayinMethods;
use Illuminate\Http\Request;

class PayinMethodsController extends Controller
{
    /**
     * Retrieve all payin methods and filter if query exists
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $query = PayinMethods::query();

        // Apply filters if query parameters exist
        if ($request->query()) {
            foreach ($request->query() as $key => $value) {
                $query->where($key, 'like', '%' . $value . '%');
            }
        }

        $payinMethods = $query->paginate(15);

        // Return the view with the paginated results
        return view('admin.payin_methods.index', compact('payinMethods'));
    }


    public function create()
    {
        return view('admin.payin_methods.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'method_name' => 'required|string|max:255',
            'gateway' => 'required|string|max:255',
            'country' => 'sometimes|string|max:255',
            'currency' => 'required|string|max:10',
            'payment_mode' => 'nullable|string|max:50',
            'charges_type' => 'required|string|in:fixed,percentage,combined',
            'fixed_charge' => 'nullable|numeric',
            'float_charge' => 'nullable|numeric',
            'settlement_time' => 'nullable|string|max:50',
            'pro_fixed_charge' => 'nullable|numeric',
            'pro_float_charge' => 'nullable|numeric',
            'minimum_deposit' => 'nullable|numeric',
            'maximum_deposit' => 'nullable|numeric',
            'minimum_charge' => 'nullable|numeric',
            'maximum_charge' => 'nullable|numeric',
            'cutoff_hrs_start' => 'nullable|string|max:10',
            'cutoff_hrs_end' => 'nullable|string|max:10',
            'Working_hours_start' => 'nullable|string|max:10',
            'Working_hours_end' => 'nullable|string|max:10',
        ]);
        if ($request->payment_mode == null) {
            $validatedData['payment_mode'] = 'bankTransfer';
        }

        if(empty($request->country)) {
            $validatedData['country'] = 'global';
        }
        
        PayinMethods::create($validatedData);
        return redirect()->route('admin.payin_methods.index')->with('success', 'Payment method created successfully.');
    }

    public function show(PayinMethods $payinMethod)
    {
        return view('admin.payin_methods.show', compact('payinMethod'));
    }

    public function edit($payinMethod)
    {
        $payinMethod = PayinMethods::findOrFail($payinMethod);
        // return response()->json($payinMethod);
        return view('admin.payin_methods.edit', compact('payinMethod'));
    }

    public function update(Request $request, PayinMethods $payinMethod)
    {
        $validatedData = $request->validate([
            'method_name' => 'required|string|max:255',
            'gateway' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'payment_mode' => 'nullable|string|max:50',
            'charges_type' => 'required|string|in:fixed,percentage,combined',
            'fixed_charge' => 'nullable|numeric',
            'float_charge' => 'nullable|numeric',
            'settlement_time' => 'nullable|string|max:50',
            'pro_fixed_charge' => 'nullable|numeric',
            'pro_float_charge' => 'nullable|numeric',
            'minimum_deposit' => 'nullable|numeric',
            'maximum_deposit' => 'nullable|numeric',
            'minimum_charge' => 'nullable|numeric',
            'maximum_charge' => 'nullable|numeric',
            'cutoff_hrs_start' => 'nullable|string|max:10',
            'cutoff_hrs_end' => 'nullable|string|max:10',
            'Working_hours_start' => 'nullable|string|max:10',
            'Working_hours_end' => 'nullable|string|max:10',
        ]);
        if ($request->payment_mode == null) {
            $validatedData['payment_mode'] = 'bankTransfer';
        }

        $payinMethod->update($validatedData);
        return redirect()->route('admin.payin_methods.index')->with('success', 'Payment method updated successfully.');
    }

    public function destroy(PayinMethods $payinMethod)
    {
        $payinMethod->delete();
        return redirect()->route('admin.payin_methods.index')->with('success', 'Payment method deleted successfully.');
    }
}
