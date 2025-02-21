<?php

namespace Modules\Currencies\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
                    'logo_url' => "https://cdn.yativo.com/" . strtolower($cur->currency_country) . ".svg",
                    'created_at' => $cur->created_at,
                    'updated_at' => $cur->updated_at,
                    'deleted_at' => $cur->deleted_at,
                    'can_hold_balance' => $cur->can_hold_balance,
                    'currency_country' => $cur->currency_country,
                    'is_active' => $cur->is_active
                ];
            });

            if ($currencies->isNotEmpty()) {
                return get_success_response($currencies);
            }

            return get_error_response(['error' => "No currency found"]);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
}
