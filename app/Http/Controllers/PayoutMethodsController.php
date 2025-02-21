<?php

namespace App\Http\Controllers;

use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayoutMethodsController extends Controller
{
    public function payoutFilter(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "country" => "required",
                "currency" => "required",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }
            
            $filters = [
                "country" => $request->country,
                "currency"=> $request->currency,
            ];
            $payout = payoutMethods::where($filters)->get();
            return get_success_response($payout);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    } 
}
