<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{

    /**
     * Store a newly created coupon in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'coupon_code'       => 'required|unique:coupons|max:255',
            'coupon_discount'   => 'required|numeric',
            'coupon_expires_at' => 'required|date',
            'coupon_status'     => 'required|in:active,inactive',
            'coupon_type'       => 'required|in:fixed,percentage',
        ]);

        if ($validate->fails()) {
            return get_error_response(['error' => $validate->errors()->toArray()]);
        }

        $coupon = Coupon::create($validate->validated());

        return get_success_response(['Coupon created successfully.']);
    }

    /**
     * Apply a coupon to the current cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function apply(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'code' => 'required|exists:coupons,code',
        ]);

        if ($validate->fails()) {
            return get_error_response(['error' => $validate->errors()->toArray()]);
        }

        $coupon = Coupon::where('code', $validate->validated())->first();

        if ($coupon->isValid()) {
            $request->session()->put('coupon', $coupon);
            return get_success_response(['Coupon applied successfully.']);
        }

        return get_success_response(['Invalid coupon code.']);
    }

}
