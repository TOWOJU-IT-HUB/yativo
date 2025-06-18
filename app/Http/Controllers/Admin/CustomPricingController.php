<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CustomPricing;
use App\Models\User;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CustomPricingController extends Controller
{
    public function __construct()
    {
        if(!Schema::hasColumn('custom_pricings', 'gateway_type')) {
            Schema::table('custom_pricings', function(Blueprint $table) {
                $table->string('gateway_type')->nullable();
            });
        }
    }

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
            'gateway_type' => 'required',
            'gateway_id' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $type = $request->gateway_type;

                    if ($type === 'virtual_card') {
                        $valid = ['card_creation', 'topup', 'charge_back', 'card_termination', 'card_decline'];
                        if (!in_array($value, $valid)) {
                            return $fail("The selected $attribute is invalid for virtual card.");
                        }
                    } elseif ($type === 'virtual_account') {
                        $valid = ['mxn_usd', 'usd', 'eur', 'mxn', 'brl', 'mxnb'];
                        if (!in_array($value, $valid)) {
                            return $fail("The selected $attribute is invalid for virtual account.");
                        }
                    } elseif (in_array($type, ['payin', 'payout'])) {
                        if ($request->gateway_type === 'payout') {
                            $request->gateway_id = PayoutMethods::findOrFail($request->gateway_id)->id;
                        } elseif ($request->gateway_type === 'payin') {
                            $request->gateway_id = PayinMethods::findOrFail($request->gateway_id)->id;
                        }
                    }
                }
            ],
            'fixed_charge' => 'required|numeric|min:0',
            'float_charge' => 'required|numeric|min:0|max:100',
        ]);

       
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
