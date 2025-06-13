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
            'gateway_type' => 'required|in:payin,payout,virtual_card,virtual_account',
            'gateway_id' => 'required',
            'fixed_charge' => 'required|numeric',
            'float_charge' => 'required|numeric',
        ]);

        // Handle gateway_id validation based on gateway_type
        if ($validated['gateway_type'] === 'payout') {
            $validated['gateway_id'] = PayoutMethods::findOrFail($validated['gateway_id'])->id;
        } elseif ($validated['gateway_type'] === 'payin') {
            $validated['gateway_id'] = PayinMethods::findOrFail($validated['gateway_id'])->id;
        } elseif (in_array($validated['gateway_type'], ['virtual_card', 'virtual_account'])) {
            if ($validated['gateway_id'] !== $validated['gateway_type']) {
                return redirect()->back()->withErrors(['gateway_id' => 'Invalid gateway ID for selected gateway type.']);
            }
        }

        // Create pricing
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
