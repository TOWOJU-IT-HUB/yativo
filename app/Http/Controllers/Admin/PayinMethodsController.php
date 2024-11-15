<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayinMethods;
use Illuminate\Http\Request;

class PayinMethodsController extends Controller
{
    public function index()
    {
        $payinMethods = PayinMethods::paginate(15);
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
            'payment_mode' => 'required|string|max:50',
            'charges_type' => 'required|string|max:50',
            'fixed_charge' => 'required|numeric',
            'float_charge' => 'required|numeric',
            'settlement_time' => 'required|string|max:50',
            'pro_fixed_charge' => 'nullable|numeric',
            'pro_float_charge' => 'nullable|numeric',
            'minimum_deposit' => 'required|numeric',
            'maximum_deposit' => 'required|numeric',
            'minimum_charge' => 'required|numeric',
            'maximum_charge' => 'required|numeric',
            'cutoff_hrs_start' => 'required|string|max:10',
            'cutoff_hrs_end' => 'required|string|max:10',
            'Working_hours_start' => 'required|string|max:10',
            'Working_hours_end' => 'required|string|max:10',
        ]);

        $payinMethod->update($validatedData);
        return redirect()->route('admin.payin_methods.index')->with('success', 'Payment method updated successfully.');
    }

    public function destroy(PayinMethods $payinMethod)
    {
        $payinMethod->delete();
        return redirect()->route('admin.payin_methods.index')->with('success', 'Payment method deleted successfully.');
    }
}
