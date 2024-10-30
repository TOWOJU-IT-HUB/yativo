<?php

namespace Modules\Beneficiary\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Monnify\App\Services\MonnifyService;


class BeneficiaryController extends Controller
{
    public array $data = [];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $per_page = $request->per_page ?? 20;

            $query = Beneficiary::with(['payment_object'])->whereUserId(active_user())->where('is_archived', false)->paginate($per_page);

            if ($query) {
                return paginate_yativo($query);
            }
            return get_error_response(['error' => 'Currently unable to retrieve beneficiaries']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "recipient_type" => "required|in:individual,business",
                "customer_name" => "required",
                "customer_email" => "required",
                "customer_nickname" => "sometimes",
                "country" => "required",
                "customer_address" => "required",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $data['user_id'] = auth()->id();
            $data['recipient_type'] = $request->recipient_type;
            $data['customer_name'] = $request->customer_name;
            $data['customer_email'] = $request->customer_email;
            $data['customer_nickname'] = $request->customer_nickname ?? $request->customer_name;
            $data['country'] = $request->country;
            $data['customer_address'] = $request->customer_address;

            if ($save = Beneficiary::create($data)) {
                if (isApi())
                    return get_success_response($save);

            }
            return get_error_response(['error' => 'Currently unable to add new beneficiaries']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        try {
            $beneficiary = Beneficiary::with('payment_object')->whereUserId(active_user())->where('id', $id)->first();
            if ($beneficiary) {
                return get_success_response($beneficiary);
            }
            return get_error_response(['error' => "Beneficiary with the provided data not found"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $validate = Validator::make($request->all(), [
                "recipient_type" => "required|in:individual,business",
                "customer_name" => "required",
                "customer_email" => "required",
                "customer_nickname" => "sometimes",
                "country" => "required",
                "customer_address" => "required",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $data = Beneficiary::whereId($id)->whereUserId(auth()->id())->first();
            if(!$data) {
                return get_error_response(['error' => "Beneficiary not found!"]);
            }
            $data->user_id = auth()->id();
            $data->recipient_type = $request->recipient_type;
            $data->customer_name = $request->customer_name;
            $data->customer_email = $request->customer_email;
            $data->customer_nickname = $request->customer_nickname ?? $request->customer_name;
            $data->country = $request->country;
            $data->customer_address = $request->customer_address;
            ;
            if ($data->save()) {
                    return get_success_response(['message' => "Beneficiary updated successfully"]);

            }
            return get_error_response(['error' => 'Currently unable to update beneficiari']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function archieve(Request $request, $id)
    {
        try {
            $beneficiary = Beneficiary::whereUserId(active_user())->where('id', $id)->first();
            if ($beneficiary) {
                $beneficiary->is_archived = true;
                $beneficiary->save();
                return get_success_response(['message' => 'Beneficiary archived successfully']);
            }
            return get_error_response(['error' => 'Beneficiary not found']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function unarchieve(Request $request, $id)
    {
        try {
            $beneficiary = Beneficiary::whereUserId(active_user())->where('id', $id)->first();
            if ($beneficiary) {
                $beneficiary->is_archived = false;
                $beneficiary->save();
                return get_success_response(['message' => 'Beneficiary unarchived successfully']);
            }
            return get_error_response(['error' => 'Beneficiary not found']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $beneficiary = Beneficiary::whereUserId(active_user())->where('id', $id)->first();
            if ($beneficiary->delete()) {
                return get_success_response(['message' => 'Beneficiary deleted successfully']);
            }
            return get_error_response(['error'  =>"Beneficiary with the provided data not found"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
}
