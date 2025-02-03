<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Handles PIN verification for transactions.
 */
class PinVerificationController extends Controller
{
    /**
     * Update or set new transaction PIN for user.
     *
     * @param Request $request
     * @return mixed
     */
    public function updatePin(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'pin' => 'required|max:4',
                'old_pin' => 'sometimes|string|max:4',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $user = User::find(auth()->id());

            if ($user->transaction_pin) {
                if (!password_verify($request->old_pin, $user->transaction_pin)) {
                    return get_error_response(['error' => 'Invalid old PIN provided']);
                }
            }

            $user->transaction_pin = bcrypt($request->pin);
            if ($user->save()) {
                return get_success_response(['message' => "Transaction PIN updated successfully"]);
            }
            return get_error_response(['error' => "Error updating transaction PIN"]);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Verify if stored PIN matches incoming PIN.
     * 
     * @param Request $request
     * @return mixed
     */
    public function verifyPin(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'pin' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $user = User::find(auth()->id());
            if (!password_verify($request->pin, $user->transaction_pin)) {
                return get_error_response(['error' => 'Invalid PIN provided']);
            }
            return get_success_response(['message' => "Transaction PIN verified successfully"]);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
}
