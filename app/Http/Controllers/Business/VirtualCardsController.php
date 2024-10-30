<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;
use Towoju5\Bitnob\Bitnob;

class VirtualCardsController extends Controller
{
    public $card;
    public function __construct()
    {
        $bitnob = new Bitnob();
        $this->card = $bitnob->cards();

        $this->middleware('auth:api')->except(['regUser','verifyUser']);
    }

    public function verifyUser()
    {
        try {
            $verify = $this->card->verifyUser();
            return get_success_response(['verify' => $verify]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()]);
        }
    }

    /**
     * Register a customer for virtual card creation.
     * 
     * @return array
     */
    public function regUser(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "customerId"    => "required|exists:customers,customer_id",
                "customerEmail" => "required|email",
                "idNumber"      => "required",
                "idType"        => "required",
                "firstName"     => "required",
                "lastName"      => "required",
                "phoneNumber"   => "required",
                "city"          => "required",
                "state"         => "required",
                "country"       => "required",
                "zipCode"       => "required",
                "line1"         => "required",
                "houseNumber"   => "required|numeric",
                "idImage"       => "required|email|image",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $req = $this->card->regUser($validate);
            $customer  = Customer::whereCustomerId($request->customerId)->first();
            if (isset($req['status']) && $req['status'] == true) {
                $customer->can_create_virtual_card = true;
                $customer->save();
                $result = get_success_response([
                    'success' => "User registered successfully."
                ]);
            } else {
                $result = get_error_response(['error' => "User registration failed, please check your payload is correct."]);
            }
            
            return $result;
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }


    public function create(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "customerId"    => "required|exists:customers,customer_id",
                // 'customerEmail' => 'required|email',
                'amount' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $cust = Customer::whereId($request->customerId)->first();

            $data = [
                'customerEmail' => $cust->customerEmail,
                'cardBrand' => 'visa',
                'cardType' => 'virtual',
                'reference' => generate_uuid(),
                'amount' => $request->amount,
            ];

            $bitnob = new Bitnob();
            $cards  = $bitnob->cards();
            $create = $cards->create($data);
            return get_success_response($create);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function show($cardId)
    {
        try {
            $card = $this->card->getCard($cardId);
            return get_success_response(['card' => $card]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $cardId)
    {
        try {
            $validate = Validator::make($request->all(), [
                "action" => "required|in:freeze,unfreeze"
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $card = $this->card->action($request->action, $cardId);
            return get_success_response(['action' => $card]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()]);
        }
    }

    public function transactions($cardId)
    {
        try {
            $card = $this->card->getTransaction($cardId);
            return get_success_response(['transactions' => $card]);
        } catch (\Exception $e) {
            return get_error_response(['error' => $e->getMessage()]);
        }
    }
}
