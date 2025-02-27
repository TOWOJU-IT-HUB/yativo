<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CustomPricing;
use App\Models\User;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Http\Request;

class CustomPricingController extends Controller
{
    public function index()
    {
        $customPricings = CustomPricing::with('user')->paginate(per_page())->withQueryString();
        return view('admin.custom-pricing.index', compact('customPricings'));
    }

    public function create()
    {
        $users = Business::with('user')->get();
        $payinGateways = PayinMethods::all();
        $payoutGateways = payoutMethods::all();
        return view('admin.custom-pricing.create', compact('users', 'payinGateways', 'payoutGateways'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'gateway_type' => 'required|in:payin,payout',
            'gateway_id' => 'required|integer', // Will be dynamically handled based on gateway type
            'fixed_charge' => 'required|numeric',
            'float_charge' => 'required|numeric',
        ]);

        // If gateway type is 'payout', ensure gateway_id exists in PayoutMethods
        if ($validated['gateway_type'] === 'payout') {
            $validated['gateway_id'] = payoutMethods::findOrFail($validated['gateway_id'])->id;
        } else {
            // If gateway type is 'payin', ensure gateway_id exists in PayinMethods
            $validated['gateway_id'] = PayinMethods::findOrFail($validated['gateway_id'])->id;
        }

        // Create the custom pricing
        CustomPricing::create($validated);

        return redirect()->route('admin.custom-pricing.index')->with('success', 'Custom pricing added successfully.');
    }

    public function destroy(CustomPricing $customPricing)
    {
        $customPricing->delete();
        return redirect()->route('admin.custom-pricing.index')->with('success', 'Custom pricing deleted successfully.');
    }

    public function getGateways(Request $request)
    {
        $gatewayType = $request->input('gateway_type');

        if ($gatewayType == 'payin') {
            $gateways = PayinMethods::all();
        } elseif ($gatewayType == 'payout') {
            $gateways = payoutMethods::all();
        } else {
            return response()->json(['error' => 'Invalid gateway type'], 400);
        }

        return response()->json($gateways);
    }
}
