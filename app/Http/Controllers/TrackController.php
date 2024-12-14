<?php

namespace App\Http\Controllers;

use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TrackController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $datas = Track::whereTrackId($id)->whereUserId(active_user())->latest()->get();
            return get_success_response($datas);
        } catch (\Throwable $th) {
            return get_error_response($th->getMessage());
        }
    }

    public function track(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "txn_id" => "required",
            "txn_type" => "required"
        ]);

        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray(), 411, "Validation error");
        }

        $where = [
            "quote_id" => $request->txn_id,
            "transaction_type" => $request->txn_type
        ];

        try {
            $datas = Track::where($where)->whereUserId(active_user())->latest()->first();
            return get_success_response($datas);
        } catch (\Throwable $th) {
            return get_error_response($th->getMessage());
        }
    }
}


