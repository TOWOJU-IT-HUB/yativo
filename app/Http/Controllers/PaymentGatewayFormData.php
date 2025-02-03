<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Handles payment gateway form data
 */
class PaymentGatewayFormData extends Controller
{
    /**
     * Get all payment gateway form data
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            $lists = PaymentGatewayFormData::orderBy("gateway_name", "asc")->get();
            return get_success_response($lists);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Store new payment gateway form data
     * 
     * @param Request $request
     */
    public function store(Request $request)
    {
        try {
            $lists = PaymentGatewayFormData::create($request->all());
        } catch (\Throwable $th) {
        }
    }

    /**
     * Get payment gateway form data by ID
     *
     * @param int $id
     */
    public function show($id)
    {
    }

    /**
     * Update payment gateway form data
     *
     * @param Request $request
     * @param int $id
     */
    public function update(Request $request, $id)
    {
    }

}
