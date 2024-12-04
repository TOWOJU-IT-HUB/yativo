<?php

namespace App\Http\Controllers;

use App\Models\BeneficiaryFoems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class BeneficiaryFoemsController extends Controller
{
    public function get()
    {
        try {
            $carry = BeneficiaryFoems::get();
            return get_success_response($carry);
        } catch (\Throwable $th) {
            get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $form = cache()->remember('beneficiary_form_'.$id, 3600, function() use ($id) {
                return BeneficiaryFoems::whereGatewayId($id)->first();
            });
            
            if(!$form OR is_null($form)) {
                return get_error_response(['error' => 'Record not found']);
            }
            return get_success_response($form->form_data);        } catch (\Throwable $th) {
            return get_error_response(['error'  =>$th->getMessage()]);
        } 
    }

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'gateway_id' => 'required',
                'currency'   => 'required',
                'form_data'  => 'array|required'
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }
            
            $record = BeneficiaryFoems::updateOrCreate(
                ['gateway_id' => $request->gateway_id],
                $validate->validated()
            );

            if(!$record) {
                return get_error_response(['error' => 'Unable to create record']);
            }
            return get_success_response($record);
        } catch (\Throwable $th) {
            return get_error_response(['error'=> $th->getMessage()]);
        }    }
}





