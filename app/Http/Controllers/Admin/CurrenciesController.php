<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\DataTables\UsersDataTable;
use Modules\Currencies\app\Models\Currency;

class CurrenciesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $currencies = Currency::where('is_active', true)->get()->map(function ($cur) {
                return [
                    'id' => $cur->id,
                    'wallet' => $cur->wallet,
                    'main_balance' => $cur->main_balance,
                    'ledger_balance' => $cur->ledger_balance,
                    'currency_icon' => $cur->currency_icon,
                    'currency_name' => $cur->currency_name,
                    'balance_type' => $cur->balance_type,
                    'currency_full_name' => $cur->currency_full_name,
                    'decimal_places' => $cur->decimal_places,
                    'logo_url' => "https://cdn.yativo.com/ " . strtolower($cur->currency_country) . ".svg",
                    'created_at' => $cur->created_at,
                    'updated_at' => $cur->updated_at,
                    'deleted_at' => $cur->deleted_at,
                    'can_hold_balance' => $cur->can_hold_balance,
                    'currency_country' => $cur->currency_country,
                    'is_active' => $cur->is_active
                ];
            });
            
            return view('admin.currencies.index', compact('currencies'));
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.currencies.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_name' => 'required|string|max:255',
            'currency_full_name' => 'required|string|max:255',
            'currency_icon' => 'nullable|string',
            'currency_country' => 'required|string|max:255',
            'decimal_places' => 'required|integer|min:0',
            'can_hold_balance' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $currency = Currency::create([
            'wallet' => uniqid(),
            'main_balance' => 0.00,
            'ledger_balance' => 0.00,
            'currency_icon' => $request->input('currency_icon'),
            'currency_name' => $request->input('currency_name'),
            'balance_type' => 'fiat',
            'currency_full_name' => $request->input('currency_full_name'),
            'decimal_places' => $request->input('decimal_places'),
            'can_hold_balance' => $request->has('can_hold_balance'),
            'currency_country' => $request->input('currency_country'),
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->route('admin.currencies.index')->with('success', 'Currency created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Currency $currency)
    {
        $currencyData = [
            'id' => $currency->id,
            'wallet' => $currency->wallet,
            'main_balance' => $currency->main_balance,
            'ledger_balance' => $currency->ledger_balance,
            'currency_icon' => $currency->currency_icon,
            'currency_name' => $currency->currency_name,
            'balance_type' => $currency->balance_type,
            'currency_full_name' => $currency->currency_full_name,
            'decimal_places' => $currency->decimal_places,
            'logo_url' => "https://cdn.yativo.com/ " . strtolower($currency->currency_country) . ".svg",
            'created_at' => $currency->created_at,
            'updated_at' => $currency->updated_at,
            'deleted_at' => $currency->deleted_at,
            'can_hold_balance' => $currency->can_hold_balance,
            'currency_country' => $currency->currency_country,
            'is_active' => $currency->is_active
        ];

        return view('admin.currencies.show', compact('currencyData'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Currency $currency)
    {
        return view('admin.currencies.edit', compact('currency'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Currency $currency)
    {
        $validator = Validator::make($request->all(), [
            'currency_name' => 'required|string|max:255',
            'currency_full_name' => 'required|string|max:255',
            'currency_icon' => 'nullable|string',
            'currency_country' => 'required|string|max:255',
            'decimal_places' => 'required|integer|min:0',
            'can_hold_balance' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $currency->update([
            'currency_icon' => $request->input('currency_icon'),
            'currency_name' => $request->input('currency_name'),
            'currency_full_name' => $request->input('currency_full_name'),
            'decimal_places' => $request->input('decimal_places'),
            'can_hold_balance' => $request->has('can_hold_balance'),
            'currency_country' => $request->input('currency_country'),
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->route('admin.currencies.index')->with('success', 'Currency updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency)
    {
        $currency->delete();

        return redirect()->route('admin.currencies.index')->with('success', 'Currency deleted successfully.');
    }

}