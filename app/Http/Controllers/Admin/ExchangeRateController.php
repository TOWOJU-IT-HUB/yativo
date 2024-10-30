<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Http\Request;
use Validator;

class ExchangeRateController extends Controller
{
    public function index()
    {
        $exchangeRates = ExchangeRate::all();
        return view('admin.exchange_rates.index', compact('exchangeRates'));
    }

    public function create()
    {
        $exchangeRates = ExchangeRate::all();
        $payoutMethods = payoutMethods::all();
        $payinMethods = PayinMethods::all();
        return view('admin.exchange_rates.create', compact('exchangeRates', 'payoutMethods', 'payinMethods'));
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            "gateway_id" => 'required|string',
            "rate_type" => 'required|string',
            "float_percentage" => 'required|string',
            // "float_amount" => 'required|numeric',
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        ExchangeRate::create($validate->validated());
        return redirect()->route('admin.exchange_rates.index')->with('success', 'Exchange rate created successfully.');
    }

    public function edit($id)
    {
        $exchangeRate = ExchangeRate::findOrFail($id);
        $exchangeRates = ExchangeRate::all();
        return view('admin.exchange_rates.edit', compact('exchangeRate', 'exchangeRates'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'rate_type' => 'required|string',
            'gateway' => 'required|string',
            'float_percentage' => 'required|numeric',
        ]);

        $exchangeRate = ExchangeRate::findOrFail($id);
        $exchangeRate->update($request->all());
        return redirect()->route('admin.exchange_rates.index')->with('success', 'Exchange rate updated successfully.');
    }

    public function destroy($id)
    {
        $exchangeRate = ExchangeRate::findOrFail($id);
        $exchangeRate->delete();
        return redirect()->route('admin.exchange_rates.index')->with('success', 'Exchange rate deleted successfully.');
    }
}
